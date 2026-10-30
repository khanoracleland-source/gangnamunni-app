import { Injectable, Logger } from '@nestjs/common';
import * as nodemailer from 'nodemailer';

/** includes/mail_helper.php: addresses that can never receive mail (RFC 2606 / test domains) are skipped, not sent. */
export function reservedMailDomain(addr: string): boolean {
  const domain = addr.slice(addr.lastIndexOf('@') + 1).toLowerCase();
  if (['example.com', 'example.net', 'example.org', 'test.com', 'test.local'].includes(domain)) return true;
  return ['.test', '.example', '.invalid', '.localhost', '.local'].some((s) => domain.endsWith(s));
}

export interface MailResult { success: boolean; error?: string }

/**
 * mail_send(0, ...) of the PHP site: the Mailcow admin@ account over SMTP submission (587, STARTTLS, certificate not verified
 * because it is our own server). SMTP_PASSWORD must be set in .env; without it nothing is sent and the caller is told so.
 */
@Injectable()
export class MailService {
  private readonly log = new Logger(MailService.name);
  private transport: nodemailer.Transporter | null = null;

  private get(): nodemailer.Transporter | null {
    const pass = process.env.SMTP_PASSWORD || '';
    if (!pass) return null;
    this.transport ??= nodemailer.createTransport({
      host: process.env.SMTP_HOST || 'mail.seoulbeauty.app',
      port: Number(process.env.SMTP_PORT || 587),
      secure: false,
      requireTLS: true,
      tls: { rejectUnauthorized: false },
      auth: { user: process.env.SMTP_USER || 'admin@seoulbeauty.app', pass },
      connectionTimeout: 30_000, greetingTimeout: 30_000, socketTimeout: 30_000,
    });
    return this.transport;
  }

  async send(to: string, subject: string, html: string): Promise<MailResult> {
    if (/[\r\n]/.test(to) || /[\r\n]/.test(subject)) return { success: false, error: 'invalid header value' };
    if (reservedMailDomain(to)) return { success: false, error: '유효한 수신자가 없습니다. (예약/테스트 도메인 제외)' };
    const t = this.get();
    if (!t) return { success: false, error: 'SMTP_PASSWORD is not set' };
    const text = html.replace(/<(br|\/p|\/div|\/li)\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '').replace(/\n{3,}/g, '\n\n').trim();
    try {
      await t.sendMail({
        from: { name: process.env.SMTP_FROM_NAME || 'Seoul Beauty Admin', address: process.env.SMTP_USER || 'admin@seoulbeauty.app' },
        to, subject, text, html,
      });
      return { success: true };
    } catch (e) {
      this.log.error(`smtp send failed: ${(e as Error).message}`);
      return { success: false, error: (e as Error).message };
    }
  }
}

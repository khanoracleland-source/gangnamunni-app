import { safeBackground, safeLink } from './home.service';

describe('index.php parity helpers', () => {
  it('safeLink mirrors home_safe_link', () => {
    expect(safeLink('hospitals.php?treatment=x')).toBe('hospitals.php?treatment=x');
    expect(safeLink('/events.php')).toBe('/events.php');
    expect(safeLink('//evil.com')).toBe('#');
    expect(safeLink('javascript:alert(1)')).toBe('#');
    expect(safeLink('https://ok.example/a')).toBe('https://ok.example/a');
    expect(safeLink('')).toBe('#');
    expect(safeLink('bad\u0001')).toBe('#');
  });
  it('safeBackground mirrors home_safe_background', () => {
    expect(safeBackground('#abc')).toBe('#abc');
    expect(safeBackground('linear-gradient(135deg, #AC1830, #D4455A)')).toBe('linear-gradient(135deg, #AC1830, #D4455A)');
    expect(safeBackground('url(x)')).toBe('linear-gradient(135deg, #AC1830, #D4455A)');
  });
});

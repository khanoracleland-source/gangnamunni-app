import { formatBodyParts, imageUrlKeys, toOption } from './hospital-content.service';

describe('detail.php parity helpers', () => {
  it('formatBodyParts matches PHP format_treatment_body_parts', () => {
    expect(formatBodyParts('이마 || (미간) || [[눈가]] ||  ')).toBe('이마, 미간, 눈가');
    expect(formatBodyParts(null)).toBe('');
  });
  it('toOption builds display name, dosage and discount like PHP', () => {
    const o = toOption({ id: 5, option_name: '프리미엄', body_part: '(이마)', dosage_value: '50.00', dosage_unit: 'U', regular_price: 100000, price: 75000 }, '주름 보톡스');
    expect(o.displayName).toBe('주름 보톡스 · 프리미엄 · 이마 · 50U');
    expect(o.discountRate).toBe(25);
    const plain = toOption({ id: 6, dosage_value: 0, regular_price: 0, price: 30000 }, '필러');
    expect(plain.displayName).toBe('필러');
    expect(plain.discountRate).toBe(0);
  });
  it('imageUrlKeys matches PHP $image_url_keys', () => {
    expect(imageUrlKeys('https://cdn.x/uploads/a/logo.png?v=1')).toEqual(['https://cdn.x/uploads/a/logo.png?v=1', '/uploads/a/logo.png', 'uploads/a/logo.png', 'logo.png']);
    expect(imageUrlKeys('/uploads/a/logo.png')).toEqual(['/uploads/a/logo.png', 'uploads/a/logo.png', 'logo.png']);
    expect(imageUrlKeys('')).toEqual([]);
  });
});

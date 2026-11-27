import { deniedDetail } from './push-token.logic';

describe('push token: database refusals in the log', () => {
  it('names the command, column and table of a column refusal', () => {
    expect(deniedDetail("INSERT command denied to user 'sb_api_ro'@'localhost' for column 'pt_token' in table 'g5_push_tokens'")).toBe('INSERT pt_token g5_push_tokens');
    expect(deniedDetail("SELECT command denied to user 'a'@'b' for column 'pt_use' in table 'g5_push_tokens'")).toBe('SELECT pt_use g5_push_tokens');
  });

  it('keeps nothing of any other message, such as a duplicate entry that quotes a token', () => {
    expect(deniedDetail("Duplicate entry 'abcdefSECRETTOKEN' for key 'pt_token'")).toBe('');
    expect(deniedDetail(undefined)).toBe('');
  });
});

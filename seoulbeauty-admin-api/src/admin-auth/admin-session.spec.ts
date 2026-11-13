import { parseAdminSession } from './admin-session.service';

describe('parseAdminSession', () => {
  it('signed out', () => {
    expect(parseAdminSession({ loggedIn: false, admin: null })).toEqual({ loggedIn: false, admin: null });
  });
  it('signed in: platform account has no hospital', () => {
    expect(parseAdminSession({ loggedIn: true, admin: { id: '3', username: 'root', name: '최고', role: 'super_admin', hospitalId: null } }))
      .toEqual({ loggedIn: true, admin: { id: 3, username: 'root', name: '최고', role: 'super_admin', hospitalId: null } });
  });
  it('signed in: hospital account keeps its hospital', () => {
    expect(parseAdminSession({ loggedIn: true, admin: { id: 9, username: 'h', name: 'h', role: 'hospital_admin', hospitalId: 12 } })?.admin?.hospitalId).toBe(12);
  });
  it.each([
    null, 'ok', [], {}, { loggedIn: 'yes' }, { loggedIn: true }, { loggedIn: true, admin: null },
    { loggedIn: true, admin: { id: 0, role: 'super_admin' } }, { loggedIn: true, admin: { id: 5 } }, { loggedIn: true, admin: { id: 5, role: 7 } },
    { error: 'session_unavailable' },
  ])('anything else is rejected: %j', (body) => {
    expect(parseAdminSession(body)).toBeNull();
  });
});

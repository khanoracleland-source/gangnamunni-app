// Generate a self-running DOM regression fixture: node tests/regression/hospital_sections_browser.cjs /tmp/hospital-sections-test.html
// Open the generated file in a browser. No database or network is used.
const fs = require('node:fs');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const source = fs.readFileSync(root + '/hairwang_full/gangnamunni/assets/js/admin-hospital-sections.js', 'utf8');
const hospital = fs.readFileSync(root + '/hairwang_full/gangnamunni/admin_hospitals.php', 'utf8');
const initializer = hospital.slice(hospital.indexOf('window.initAdminHospitalImages ='), hospital.indexOf('\n</script>', hospital.indexOf('window.initAdminHospitalImages =')));
const panel = `<section id="editTreatmentsSection"><form id="assignForm" action="https://fixture.test/treatments"><input name="action" value="assign"><input name="draft" value="original"><button>Save treatment</button></form><script type="application/json" id="hospitalTreatmentData">{"images":{"1":[]},"prices":{"1":[{"price":100}]},"isSuperAdmin":true}</script></section>`;
function images(count = 0) { return `<section id="adminHospitalImagesCard"><button data-bs-target="#editHospitalImagesSection">Images</button><div id="editHospitalImagesSection"><form id="adminHospitalImageForm" action="https://fixture.test/images"><input name="action" value="add_hospital_images"><input type="file" id="adminMainImageInput" name="admin_main_image"><div id="adminMainImagePreview"></div><input type="file" id="adminInteriorImagesInput" name="admin_interior_images[]" multiple><span id="adminInteriorImageCount" data-saved-count="${count}">${count} / 10</span><div id="adminInteriorImagesPreview"></div><button>Save images</button></form></div></section>`; }
const script = `
const output = document.getElementById('results');
let mode = 'success', posts = 0, gets = 0;
const updatedPage = ${JSON.stringify(panel + images(2)).replace(/</g, '\\u003c')};
window.fetch = async (url, options = {}) => {
 if (options.method === 'POST') {
  posts++;
  const expectedUrl = options.body.get('action') === 'add_hospital_images'
   ? 'https://fixture.test/images' : 'https://fixture.test/treatments';
  assert(String(url) === expectedUrl, 'POST URL was shadowed by the action input: ' + String(url));
  if (mode === 'network') throw Error('offline');
  if (mode === 'login') return {ok:true,redirected:true,headers:new Headers({'content-type':'text/html'})};
  return {ok:true,redirected:false,headers:new Headers({'content-type':'application/json'}),json:async()=>({ok:mode !== 'validation',message: mode === 'validation' ? 'Validation error' : 'Saved'})};
 }
 gets++;
 if (mode === 'refresh-failure') throw Error('offline');
 return {ok:true,redirected:false,text:async()=>updatedPage};
};
function assert(value, message) { if (!value) throw Error(message); }
const settle = () => new Promise(resolve => setTimeout(resolve, 30));
function line(text) { const li=document.createElement('li');li.textContent=text;output.append(li); }
(async () => {
 try {
  const main = document.getElementById('adminMainImageInput');
  const interior = document.getElementById('adminInteriorImagesInput');
  const file = new File(['fixture'], 'main.png', {type:'image/png'});
  for (const input of [main,interior]) { const transfer=new DataTransfer();transfer.items.add(file);input.files=transfer.files; }
  document.getElementById('hospitalName').value='Unsaved hospital';
  document.getElementById('assignForm').requestSubmit();
  document.getElementById('assignForm').requestSubmit();
  await settle();
  assert(posts===1, 'duplicate POST');
  assert(main === document.getElementById('adminMainImageInput') && main.files.length===1 && interior.files.length===1,'treatment save discarded images');
  assert(window.HT_PRICES[1][0].price===100,'stale modal data');
  line('PASS: treatment save preserves both image inputs and refreshes modal data; duplicate submits blocked');
  let draft = document.querySelector('#assignForm input[name=draft]');draft.value='Unsaved treatment';
  document.getElementById('adminHospitalImageForm').requestSubmit(); await settle();
  assert(document.querySelector('#assignForm input[name=draft]')===draft && draft.value==='Unsaved treatment','image save discarded treatment');
  assert(document.getElementById('hospitalName').value==='Unsaved hospital','hospital draft discarded');
  assert(document.getElementById('adminMainImageInput').files.length===0,'saved files not cleared');
  assert(document.getElementById('adminInteriorImageCount').dataset.savedCount==='2','saved count stale');
  const currentInterior=document.getElementById('adminInteriorImagesInput');const transfer=new DataTransfer();transfer.items.add(file);currentInterior.files=transfer.files;currentInterior.dispatchEvent(new Event('change'));
  assert(document.getElementById('adminInteriorImageCount').textContent==='3 / 10','new preview listener uses stale count');
  line('PASS: image save preserves other drafts, updates saved count and reinitializes previews');
  for (const failure of ['validation','network','login']) {
   mode=failure;const before=gets;
   document.getElementById('adminHospitalImageForm').requestSubmit();await settle();
   assert(currentInterior===document.getElementById('adminInteriorImagesInput') && currentInterior.files.length===1,'error discarded file');
   assert(gets===before,'error triggered refresh');
   assert(document.querySelector('[role=status]').textContent.length>0,'missing error');
   line('PASS: '+failure+' preserves file selection and shows feedback');
  }
  mode='validation';
  document.getElementById('editTreatmentsSection').insertAdjacentHTML('beforeend', '<div id="testModal" class="modal show"><div class="modal-content"></div></div><form id="hiddenAction" class="d-none" action="https://fixture.test/treatments"></form>');
  document.getElementById('hiddenAction').requestSubmit();await settle();
  assert(document.querySelector('#testModal .modal-content [role=status]'),'hidden image action error is behind modal');
  document.getElementById('testModal').remove();document.getElementById('hiddenAction').remove();
  line('PASS: hidden image actions display errors inside the open modal');
  mode='refresh-failure';document.getElementById('assignForm').requestSubmit();await settle();
  const posted=posts;document.getElementById('assignForm').requestSubmit();await settle();assert(posts===posted,'saved request repeated');
  mode='success';document.querySelector('[role=status] button').click();await settle();
  assert(posts===posted,'GET retry repeated POST');
  assert(currentInterior===document.getElementById('adminInteriorImagesInput'),'retry discarded unrelated draft');
  line('PASS: refresh failure retries GET only and keeps unrelated drafts');
  document.getElementById('assignForm').requestSubmit();await settle();assert(posts===posted+1,'save remained locked after retry');
  line('PASS: saving resumes after refresh recovery');
  document.title='PASS: hospital section regressions';
 } catch(error) {line('FAIL: '+error.message);document.title='FAIL: hospital section regressions';throw error;}
})();`;
const html = `<!doctype html><meta charset="utf-8"><title>Hospital section tests</title><style>body{font:16px system-ui;margin:32px}section{border:1px solid #ccc;padding:16px;margin:16px 0}li{margin:10px}</style><h1>Hospital section regressions</h1><ol id="results"></ol><label>Hospital name <input id="hospitalName"></label>${panel}${images()}<script>${initializer}</script><script>${source}</script><script>${script}</script>`;
module.exports = html;
if (require.main === module) fs.writeFileSync(process.argv[2] || '/tmp/hospital-sections-test.html', html);

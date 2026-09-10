/* Save independent hospital sections without discarding drafts in other forms. */
(function () {
    'use strict';
    if (!document.getElementById('adminHospitalImageForm') && !document.getElementById('editTreatmentsSection') && !document.getElementById('viewTreatmentsSection')) return;
    var busy = false;
    var refreshing = false;
    var notice = document.createElement('div');
    notice.setAttribute('role', 'status');
    notice.setAttribute('aria-live', 'polite');
    notice.hidden = true;

    function feedback(container, message, error) {
        notice.className = 'alert ' + (error ? 'alert-danger' : 'alert-success');
        notice.textContent = message;
        notice.hidden = false;
        container.prepend(notice);
    }

    async function closeModals(container) {
        var modals = Array.from(container.querySelectorAll('.modal'));
        for (var modal of modals) {
            var instance = window.bootstrap && bootstrap.Modal.getInstance(modal);
            if (!instance) continue;
            if (modal.classList.contains('show')) {
                await new Promise(function (resolve) {
                    modal.addEventListener('hidden.bs.modal', resolve, { once: true });
                    instance.hide();
                });
            }
            instance.dispose();
        }
    }

    async function refreshSection(container, images) {
        var url = new URL(window.location.href);
        url.hash = '';
        url.searchParams.delete('treatment_msg');
        url.searchParams.delete('treatment_msg_type');
        var response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
        if (!response.ok || response.redirected) throw new Error('refresh');
        var page = new DOMParser().parseFromString(await response.text(), 'text/html');
        var replacement = page.getElementById(container.id);
        if (!replacement) throw new Error('refresh');
        var data;
        if (!images) {
            var dataElement = replacement.querySelector('#hospitalTreatmentData');
            if (!dataElement) throw new Error('refresh');
            data = JSON.parse(dataElement.textContent);
        }
        // The existing treatment functions read these globals; never execute fetched scripts.
        replacement.querySelectorAll('script').forEach(function (script) { script.remove(); });
        await closeModals(container);
        container.replaceChildren(...Array.from(replacement.childNodes));
        if (images) {
            var collapse = container.querySelector('#editHospitalImagesSection');
            collapse.classList.add('show');
            container.querySelector('[data-bs-target="#editHospitalImagesSection"]').setAttribute('aria-expanded', 'true');
            window.initAdminHospitalImages();
        } else {
            window.HT_IMAGES = data.images;
            window.HT_PRICES = data.prices;
            window.IS_SUPER_ADMIN = data.isSuperAdmin;
        }
    }

    document.addEventListener('submit', async function (event) {
        var form = event.target;
        var images = form.id === 'adminHospitalImageForm';
        var container = images ? form.closest('#adminHospitalImagesCard')
            : form.closest('#editTreatmentsSection, #viewTreatmentsSection');
        if (!container || event.defaultPrevented) return;
        event.preventDefault();
        if (busy) return;
        busy = true;
        var feedbackTarget = form.closest('.modal-content')
            || (form.classList.contains('d-none') ? container.querySelector('.modal.show .modal-content') || container : form);
        var body = new FormData(form);
        container.inert = true;
        container.setAttribute('aria-busy', 'true');
        var saved = false;
        try {
            var response = await fetch(form.action, {
                method: 'POST', body: body, credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            if (!response.ok || response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) {
                throw new Error('response');
            }
            var result = await response.json();
            if (!result.ok) {
                feedback(feedbackTarget, result.message || '저장하지 못했습니다. 입력 내용을 확인해 주세요.', true);
                return;
            }
            saved = true;
            await refreshSection(container, images);
            feedback(container, result.message, false);
        } catch (error) {
            if (saved) {
                // Do not repeat a successful POST if fetching the updated section failed.
                feedback(container.querySelector('.modal.show .modal-content') || container, '저장되었습니다. 최신 내용을 불러오지 못했습니다.', true);
                var retry = document.createElement('button');
                retry.type = 'button';
                retry.className = 'btn btn-outline-primary btn-sm ms-2';
                retry.textContent = '내용 다시 불러오기';
                notice.appendChild(retry);
                container.querySelectorAll('form').forEach(function (item) { item.inert = true; });
                retry.addEventListener('click', async function () {
                    if (refreshing) return;
                    refreshing = true;
                    try {
                        await refreshSection(container, images);
                        feedback(container, result.message, false);
                        busy = false;
                    } catch (_) {
                        retry.textContent = '불러오기 실패 · 다시 시도';
                    } finally { refreshing = false; }
                });
            } else {
                feedback(feedbackTarget, '저장 결과를 확인하지 못했습니다. 입력 내용은 유지됩니다. 연결 또는 로그인 상태를 확인하고, 재시도 전에 저장 여부를 확인해 주세요.', true);
            }
        } finally {
            container.inert = false;
            container.removeAttribute('aria-busy');
            // A failed refresh keeps all saves blocked until its GET-only retry succeeds.
            if (!saved || !container.querySelector('form[inert]')) busy = false;
        }
    });
})();

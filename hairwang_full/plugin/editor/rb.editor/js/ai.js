function b64toBlob(b64Data, contentType, sliceSize) {
  contentType = contentType || '';
  sliceSize = sliceSize || 512;
  var byteCharacters = atob(b64Data);
  var byteArrays = [];
  for (var offset = 0; offset < byteCharacters.length; offset += sliceSize) {
    var slice = byteCharacters.slice(offset, offset + sliceSize);
    var byteNumbers = new Array(slice.length);
    for (var i = 0; i < slice.length; i++) {
      byteNumbers[i] = slice.charCodeAt(i);
    }
    var byteArray = new Uint8Array(byteNumbers);
    byteArrays.push(byteArray);
  }
  return new Blob(byteArrays, { type: contentType });
}

function uploadImageAI(blob) {
  var formData = new FormData();
  formData.append("file", blob, "generated.png");
  var nonceElem = document.getElementById('editor_nonce');
  if (nonceElem && nonceElem.value) {
    formData.append("editor_nonce", nonceElem.value);
  } else if (typeof ed_nonce !== "undefined" && ed_nonce) {
    formData.append("editor_nonce", ed_nonce);
  }
  return fetch(g5Config.g5_editor_url + '/php/rb.upload.php', {
    method: 'POST',
    body: formData
  }).then(function (response) {
    return response.json();
  });
}

// 엔터키로 즉시 요청
document.getElementById('prompt').addEventListener('keydown', function (e) {
  if (e.key === "Enter") {
    e.preventDefault();
    document.getElementById('generateBtn').click();
  }
});

document.getElementById('generateBtn').addEventListener('click', function () {
  var prompt = document.getElementById('prompt').value.trim();
  if (prompt === '') {
    alert('생성하실 주제를 입력해 주세요.');
    return;
  }

  var btn = document.getElementById('generateBtn');
  btn.disabled = true;
  btn.textContent = '생각 중..';
  document.getElementById('result').innerHTML = '';

  // 로딩 오버레이 표시
  var overlay = document.querySelector(".loadingOverlay_ai");
  overlay.style.display = "block";

  // Gemini만 사용, taskType 필요 없음!
  var formData = new URLSearchParams();
  formData.append("prompt", prompt);

  fetch(g5Config.g5_editor_url + '/plugin/ai/ajax.generate.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: formData.toString()
  })
    .then(response => response.json())
    .then(data => {
      var editor = document.getElementById("editor");
      var accumulateCheckbox = document.getElementById('accumulateCheckbox');
      if (!(accumulateCheckbox && accumulateCheckbox.checked)) {
        editor.innerHTML = "";
      }

      // 텍스트 결과 (있으면)
      if (data.text) {
        document.getElementById("prompt_ai_img").src = "./image/ai/Gemini.svg";
        var newText = document.createElement("div");
        newText.innerHTML = data.text;
        editor.appendChild(newText);
        editor.appendChild(document.createElement("br"));
        document.getElementById('prompt').value = "";
      }

      // 이미지 결과 (있으면)
      if (data.image && data.image.length > 0) {
        // base64 → Blob 변환 후 업로드
        var blob = b64toBlob(data.image, "image/png");
        uploadImageAI(blob).then(function (uploadResponse) {
          if (uploadResponse.files && uploadResponse.files[0] && uploadResponse.files[0].url) {
            var fileUrl = uploadResponse.files[0].url;
            var resizableWrap = document.createElement("div");
            resizableWrap.classList.add("resizable_wrap");
            resizableWrap.style.position = "relative";

            var newImage = document.createElement("div");
            newImage.classList.add("resizable", "rb_ai_image");
            newImage.style.width = "512px";
            newImage.style.height = "512px";
            newImage.style.position = "relative";
            newImage.setAttribute("data-original-width", "512");
            newImage.setAttribute("data-original-height", "512");
            newImage.setAttribute("data-ratio", (512 / 512).toString());
            newImage.innerHTML = `
              <img src="${fileUrl}" alt="Generated Image" crossorigin="anonymous" draggable="false" style="width: 100%; height: 100%; object-fit: cover;">
              <div class="resize-handle"></div>
            `;
            resizableWrap.appendChild(newImage);
            editor.appendChild(resizableWrap);

            // 리사이즈 이벤트
            function updateHeight() {
              var ratio = parseFloat(newImage.getAttribute("data-ratio"));
              if (!isNaN(ratio)) {
                newImage.style.height = (newImage.offsetWidth * ratio) + "px";
              }
            }
            updateHeight();
            window.addEventListener("resize", updateHeight);

            if (typeof makeImageResizableWithObserver !== "undefined") {
              makeImageResizableWithObserver($(newImage));
            }
          } else {
            document.getElementById('result').innerHTML = '<p style="font-size: 12px; color:#f55036">이미지 업로드 실패</p>';
          }
        }).catch(function (err) {
          document.getElementById('result').innerHTML = '<p style="font-size: 12px; color:#f55036">이미지 업로드 오류</p>';
        });
      }

      if (data.error) {
        document.getElementById('result').innerHTML = '<p style="font-size: 12px; color:#f55036">오류가 있습니다.</p>';
      }

      overlay.style.display = "none";
      btn.disabled = false;
      btn.textContent = '생성하기';
    })
    .catch(error => {
      document.getElementById('result').innerHTML = '<p style="font-size: 12px; color:#f55036">오류가 있습니다.</p>';
      document.querySelector(".loadingOverlay_ai").style.display = "none";
      btn.disabled = false;
      btn.textContent = '생성하기';
    });
});

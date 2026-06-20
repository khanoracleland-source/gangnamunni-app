function removeElementsTemporarily(selectors, callback) {
    const elements = document.querySelectorAll(selectors);
    const originalStyles = [];

    elements.forEach(el => {
        originalStyles.push(el.style.display);
        el.style.display = "none";
    });

    return callback().finally(() => {
        elements.forEach((el, index) => {
            el.style.display = originalStyles[index];
        });
    });
}

function addMarginToCanvas(canvas, marginSize = 50) {
    const newCanvas = document.createElement("canvas");
    const ctx = newCanvas.getContext("2d");

    newCanvas.width = canvas.width + marginSize * 2;
    newCanvas.height = canvas.height + marginSize * 2;

    ctx.fillStyle = "white";
    ctx.fillRect(0, 0, newCanvas.width, newCanvas.height);
    ctx.drawImage(canvas, marginSize, marginSize);

    return newCanvas;
}

function captureEditor() {
    return new Promise((resolve, reject) => {
        const editor = document.getElementById("editor");

        if (!editor) {
            console.error("Error: `#editor` 요소를 찾을 수 없음");
            return reject("Missing `#editor` element");
        }

        const resizableElements = document.querySelectorAll(".resizable");

        // `.selected` 클래스 제거
        resizableElements.forEach(resizable => resizable.classList.remove("selected"));

        return html2canvas(editor, {
            useCORS: true,
            scale: window.devicePixelRatio * 3,
            allowTaint: true,
            backgroundColor: null
        }).then(editorCanvas => {
            // `.resizable`이 하나도 없으면 `#editor`만 저장
            if (resizableElements.length === 0) {
                resolve(editorCanvas);
                return;
            }

            // `.resizable`이 있을 경우 개별적으로 필터 적용
            const newCanvas = document.createElement("canvas");
            const ctx = newCanvas.getContext("2d");

            newCanvas.width = editorCanvas.width;
            newCanvas.height = editorCanvas.height;

            ctx.fillStyle = "white";
            ctx.fillRect(0, 0, newCanvas.width, newCanvas.height);
            ctx.drawImage(editorCanvas, 0, 0);

            const imagePromises = [];

            resizableElements.forEach(resizable => {
                const img = resizable.querySelector("img");
                if (!img) return;

                const imgObj = new Image();
                imgObj.crossOrigin = "anonymous";
                imgObj.src = img.src;

                const imgStyle = getComputedStyle(img);
                const resizableRect = resizable.getBoundingClientRect();
                const editorRect = editor.getBoundingClientRect();

                const imgLeft = resizableRect.left - editorRect.left;
                const imgTop = resizableRect.top - editorRect.top;
                const resizableWidth = resizable.offsetWidth;
                const resizableHeight = resizable.offsetHeight;

                const borderRadius = parseFloat(imgStyle.borderRadius) * 3;
                const borderWidth = parseFloat(imgStyle.borderWidth) * 3;
                const borderColor = imgStyle.borderColor;

                const boxShadow = imgStyle.boxShadow.match(/(-?\d+px)/g);
                const shadowOffsetX = boxShadow ? parseFloat(boxShadow[0]) * 3 : 0;
                const shadowOffsetY = boxShadow ? parseFloat(boxShadow[1]) * 3 : 0;
                const shadowBlur = boxShadow ? parseFloat(boxShadow[2]) * 3 : 0;
                const shadowColor = imgStyle.boxShadow.match(/rgba?\([\d,.\s]+\)/);

                const promise = new Promise((imgResolve) => {
                    imgObj.onload = function () {
                        ctx.save();

                        if (shadowColor) {
                            ctx.shadowColor = shadowColor[0];
                            ctx.shadowBlur = shadowBlur;
                            ctx.shadowOffsetX = shadowOffsetX;
                            ctx.shadowOffsetY = shadowOffsetY;
                        }

                        ctx.filter = imgStyle.filter;
                        ctx.globalAlpha = imgStyle.opacity;

                        const imgRatio = imgObj.width / imgObj.height;
                        const canvasRatio = resizableWidth / resizableHeight;
                        let sx, sy, sWidth, sHeight;

                        if (imgRatio > canvasRatio) {
                            sHeight = imgObj.height;
                            sWidth = sHeight * canvasRatio;
                            sx = (imgObj.width - sWidth) / 2;
                            sy = 0;
                        } else {
                            sWidth = imgObj.width;
                            sHeight = sWidth / canvasRatio;
                            sx = 0;
                            sy = (imgObj.height - sHeight) / 2;
                        }

                        ctx.beginPath();
                        ctx.roundRect(imgLeft * 3, imgTop * 3, resizableWidth * 3, resizableHeight * 3, borderRadius);
                        ctx.closePath();
                        ctx.clip();
                        ctx.drawImage(imgObj, sx, sy, sWidth, sHeight, imgLeft * 3, imgTop * 3, resizableWidth * 3, resizableHeight * 3);

                        if (borderWidth > 0) {
                            ctx.strokeStyle = borderColor;
                            ctx.lineWidth = borderWidth;
                            ctx.stroke();
                        }

                        ctx.restore();
                        imgResolve();
                    };

                    imgObj.onerror = function () {
                        console.error("Error: 이미지 로드 실패");
                        imgResolve();
                    };
                });

                imagePromises.push(promise);
            });

            Promise.all(imagePromises).then(() => {
                resolve(newCanvas);
            });
        }).catch(error => {
            console.error("html2canvas 캡처 오류:", error);
            reject(error);
        });
    });
}

/* 고도화 후 오픈예정
document.getElementById("save-pdf-btn").addEventListener("click", function () {
    removeElementsTemporarily(".resize-handle, .delete-preview, .rb-video-container", () => {
        return captureEditor().then(canvas => {
            const finalCanvas = addMarginToCanvas(canvas, 50);
            const imgData = finalCanvas.toDataURL("image/png");
            const {
                jsPDF
            } = window.jspdf;
            const pdf = new jsPDF("p", "mm", "a4");

            const pdfWidth = pdf.internal.pageSize.getWidth(); // A4 너비 (mm)
            const pdfHeight = pdf.internal.pageSize.getHeight(); // A4 높이 (mm)

            const canvasWidth = finalCanvas.width;
            const canvasHeight = finalCanvas.height;

            // PDF에 맞는 비율 계산
            const scaleFactor = pdfWidth / canvasWidth;
            const imgHeight = canvasHeight * scaleFactor;

            let positionY = 0;

            while (positionY < imgHeight) {
                pdf.addImage(imgData, "PNG", 0, -positionY, pdfWidth, imgHeight, "FAST");
                positionY += pdfHeight; // 한 페이지 크기만큼 이동

                if (positionY < imgHeight) {
                    pdf.addPage();
                }
            }

            pdf.save("editor.pdf");
        });
    });
});


document.getElementById("save-image-btn").addEventListener("click", function () {
    removeElementsTemporarily(".resize-handle, .delete-preview, .rb-video-container", () => {
        return captureEditor().then(canvas => {
            const finalCanvas = addMarginToCanvas(canvas, 50);
            const link = document.createElement("a");
            link.download = "editor.png";
            link.href = finalCanvas.toDataURL("image/png");
            link.click();
        });
    });
});

document.getElementById("save-print-btn").addEventListener("click", function () {
    removeElementsTemporarily(".resize-handle, .delete-preview, .rb-video-container", () => {
        return captureEditor().then(canvas => {
            const finalCanvas = addMarginToCanvas(canvas, 50);
            const imageData = finalCanvas.toDataURL("image/png");

            const printWindow = window.open("", "_blank");
            printWindow.document.write(`
                <html>
                <head>
                    <title>인쇄</title>
                    <style>
                        @media print {
                            body { margin: 0; padding: 0; display: flex; justify-content: center; align-items: center; }
                            img { display: block; width: 100%; height: auto; page-break-after: always; }
                        }
                    </style>
                </head>
                <body>
                    <img src="${imageData}" onload="window.print(); setTimeout(() => { window.close(); }, 500);">
                </body>
                </html>
            `);
            printWindow.document.close();
        });
    });
});

*/
window.addEventListener("message", function (event) {
    if (event.data.type === "rbeditor-submit") {
        const editorContent = document.getElementById("editor").innerHTML;
        const iframe = window.frameElement;
        const editorId = iframe.getAttribute("data-editor-id");

        window.parent.postMessage({
            type: "rbeditor-content",
            content: editorContent,
            editorId: editorId
        }, "*");
    }

    if (event.data.type === "rbeditor-set-content") {
        const editorId = event.data.editorId; // 부모 창에서 전달받은 editorId
        let content = event.data.content;

        if (content) {
            // 1. 엔티티를 디코딩하여 원래 HTML로 변환
            const tempDiv = document.createElement("div");
            tempDiv.innerHTML = content; // 자동 디코딩 수행

            // 2. 에디터에 변환된 HTML 삽입
            const editor = document.getElementById("editor");
            if (editor) {
                editor.innerHTML = tempDiv.textContent || tempDiv.innerText;
            } else {
                //console.error(`Editor with ID ${editorId} not found`);
            }
        }
    }



    /* 자동저장 전송 */
    if (event.data.type === "rbeditor-insert-content") {
        const content = event.data.content;

        // HTML 엔티티 디코딩 (태그 및 스타일 유지)
        const tempDiv = document.createElement("div");
        tempDiv.innerHTML = content;
        const decodedContent = tempDiv.innerHTML;

        // 강제로 `#regular-mode-btn` 클릭 효과 실행
        function triggerRegularMode() {
            var regularModeBtn = document.getElementById("regular-mode-btn");

            if (regularModeBtn) {
                //console.log("#regular-mode-btn 클릭 트리거 실행");
                regularModeBtn.click(); // 클릭 효과 발생
            } else {
                //console.error("#regular-mode-btn을 찾을 수 없습니다.");
            }
        }

        // RB 에디터에 내용 삽입
        function insertContent() {
            var editor = document.getElementById("editor");

            if (!editor) {
                console.error("에디터를 찾을 수 없습니다.");
                return;
            }

            editor.innerHTML = decodedContent; // 기존 내용 교체
            editor.focus(); // 포커스 유지
            //console.log("에디터 내용이 정상적으로 업데이트됨");
        }

        // `#regular-mode-btn` 클릭 효과 실행 후 내용 삽입
        triggerRegularMode();
        setTimeout(insertContent, 100); // 클릭 효과 후 약간의 지연 적용
    }



    if (event.data.type === "rbeditor-get-content") {
        //console.log("autosave 요청 수신"); // 로그 추가
        const editorContent = document.getElementById("editor").innerHTML;

        // 부모 창으로 에디터 내용 전송
        event.source.postMessage({
            type: "rbeditor-content",
            content: editorContent
        }, "*");
    }

});

/* 고도화 후 오픈예정
document.getElementById("btn_rb_autosave").addEventListener("click", function () {
    //console.log("아이프레임에서 부모 창으로 autosave-trigger 요청 전송");

    // 부모 창으로 자동 저장 실행 요청
    window.parent.postMessage({
        type: "autosave-trigger"
    }, "*");
});

document.getElementById("btn_tb_autosave_popup").addEventListener("click", function () {
    //console.log("아이프레임에서 부모 창으로 autosave 요청 전송");

    // 부모 창으로 메시지 전송
    window.parent.postMessage({
        type: "trigger-autosave-popup"
    }, "*");
});
*/
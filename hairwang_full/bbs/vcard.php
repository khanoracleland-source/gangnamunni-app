<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>연락처 저장</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: #f5f5f5;
        }
        .container {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 90%;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: #0066ff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 18px;
            margin: 10px;
        }
        .btn:active {
            background: #0052cc;
        }
        h2 {
            margin-bottom: 20px;
            color: #333;
        }
        .info {
            margin: 20px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            text-align: left;
            line-height: 1.8;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>연락처 저장</h2>
        
        <div class="info" id="contactInfo">
            <!-- 연락처 정보가 여기에 표시됩니다 -->
        </div>
        
        <a href="#" id="downloadBtn" class="btn">연락처 다운로드</a>
        <a href="#" id="copyBtn" class="btn" style="background: #666;">정보 복사하기</a>
        
        <script>
        // URL 파라미터 가져오기
        const params = new URLSearchParams(window.location.search);
        const bo_table = params.get('bo_table');
        const wr_id = params.get('wr_id');
        
        // 연락처 정보 (PHP에서 전달받을 데이터)
        const contactData = {
            name: params.get('name') || '',
            company: params.get('company') || '',
            position: params.get('position') || '',
            mobile: params.get('mobile') || '',
            email: params.get('email') || ''
        };
        
        // 정보 표시
        let infoHTML = '';
        if(contactData.name) infoHTML += `<strong>이름:</strong> ${contactData.name}<br>`;
        if(contactData.company) infoHTML += `<strong>회사:</strong> ${contactData.company}<br>`;
        if(contactData.position) infoHTML += `<strong>직책:</strong> ${contactData.position}<br>`;
        if(contactData.mobile) infoHTML += `<strong>휴대폰:</strong> ${contactData.mobile}<br>`;
        if(contactData.email) infoHTML += `<strong>이메일:</strong> ${contactData.email}<br>`;
        
        document.getElementById('contactInfo').innerHTML = infoHTML;
        
        // 다운로드 버튼
        document.getElementById('downloadBtn').onclick = function(e) {
            e.preventDefault();
            
            // vCard 생성
            let vcard = 'BEGIN:VCARD\r\nVERSION:3.0\r\n';
            if(contactData.name) {
                vcard += `FN:${contactData.name}\r\n`;
                vcard += `N:${contactData.name};;;;\r\n`;
            }
            if(contactData.company) vcard += `ORG:${contactData.company}\r\n`;
            if(contactData.position) vcard += `TITLE:${contactData.position}\r\n`;
            if(contactData.mobile) vcard += `TEL;TYPE=CELL:${contactData.mobile}\r\n`;
            if(contactData.email) vcard += `EMAIL:${contactData.email}\r\n`;
            vcard += 'END:VCARD';
            
            // Blob으로 다운로드
            const blob = new Blob([vcard], { type: 'text/vcard;charset=utf-8' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = (contactData.name || 'contact') + '.vcf';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            // 3초 후 창 닫기
            setTimeout(() => {
                window.close();
            }, 3000);
        };
        
        // 복사 버튼
        document.getElementById('copyBtn').onclick = function(e) {
            e.preventDefault();
            
            let text = '';
            if(contactData.name) text += `이름: ${contactData.name}\n`;
            if(contactData.company) text += `회사: ${contactData.company}\n`;
            if(contactData.position) text += `직책: ${contactData.position}\n`;
            if(contactData.mobile) text += `휴대폰: ${contactData.mobile}\n`;
            if(contactData.email) text += `이메일: ${contactData.email}\n`;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('연락처 정보가 복사되었습니다.');
            }).catch(() => {
                alert('복사에 실패했습니다.');
            });
        };
        </script>
    </div>
</body>
</html>
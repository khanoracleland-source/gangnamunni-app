<style>
    
    .rb_w100_div {padding-bottom: 20px; text-align: center;} /* 기본 스타일 */
    .rb_w100_div ul {margin:0 auto;} /* 내부 ul 중앙정렬 */
    
    /* 깔끔한 판다랭크 스타일 검색창 */
    .panda-search-container-<?php echo $row_mod['md_id'] ?> {
        max-width: 800px;
        margin: 0 auto;
        position: relative;
    }
    
    .panda-search-wrapper-<?php echo $row_mod['md_id'] ?> {
        position: relative;
        display: block;
        background: white;
        border-radius: 10px;
        border: 1px solid #e5e5e5;
        padding: 0;
        transition: all 0.3s ease;
    }
    
    .panda-search-wrapper-<?php echo $row_mod['md_id'] ?>:focus-within {
        box-shadow: 0 6px 25px rgba(0,0,0,0.15);
        transform: none;
    }
    
    .panda-search-container-<?php echo $row_mod['md_id'] ?> .panda-search-box-<?php echo $row_mod['md_id'] ?> {
        width: 100%;
        border: none !important;
        outline: none !important;
        padding: 16px 90px 16px 24px !important;
        font-size: 16px;
        background: transparent !important;
        color: #333;
        border-radius: 10px !important;
        height: auto !important;
        box-sizing: border-box !important;
        box-shadow: none !important;
    }
    
    .panda-search-container-<?php echo $row_mod['md_id'] ?> .panda-search-box-<?php echo $row_mod['md_id'] ?>:focus {
        outline: none !important;
        border: none !important;
        box-shadow: none !important;
    }
    
    .panda-search-container-<?php echo $row_mod['md_id'] ?> .panda-search-box-<?php echo $row_mod['md_id'] ?>::placeholder {
        color: #999 !important;
        font-size: 15px;
    }
    
    .panda-search-btn-<?php echo $row_mod['md_id'] ?> {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: #7a4efe;
        border: none;
        border-radius: 10px;
        color: white;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        white-space: nowrap;
        z-index: 10;
    }
    
    .panda-search-btn-<?php echo $row_mod['md_id'] ?>:hover {
        background: #333;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    /* 깔끔한 키워드 태그 스타일 */
    .panda-keywords-<?php echo $row_mod['md_id'] ?> {
        margin-top: 24px;
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        align-items: center;
        gap: 8px;
    }
    
    .keywords-label-<?php echo $row_mod['md_id'] ?> {
        background: linear-gradient(45deg, #ff6b6b, #ff8e53);
        color: white;
        padding: 6px 12px;
        border-radius: 16px;
        font-size: 12px;
        font-weight: 600;
        white-space: nowrap;
    }
    
    .keyword-tag-<?php echo $row_mod['md_id'] ?> {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 16px;
        padding: 6px 14px;
        font-size: 13px;
        color: #495057;
        cursor: pointer;
        transition: all 0.2s ease;
        white-space: nowrap;
    }
    
    .keyword-tag-<?php echo $row_mod['md_id'] ?>:hover {
        background: #e9ecef;
        border-color: #930000;
        color: #930000;
        transform: translateY(-1px);
    }
    
    @media all and (max-width:1024px) {
        /* 내부 ul 반응형 처리 */
        .rb_w100_div ul {padding-left: 20px; padding-right: 20px; width: 100% !important;}
        
        /* 모바일 세로 여백 줄이기 */
        .rb_w100_div {padding-top: 20px !important; padding-bottom: 20px !important; margin-top: -25px;}
        
        .panda-search-container-<?php echo $row_mod['md_id'] ?> {
            margin: 0 auto;
        }
        
        .panda-search-container-<?php echo $row_mod['md_id'] ?> .panda-search-box-<?php echo $row_mod['md_id'] ?> {
            padding: 12px 80px 12px 20px !important;
            font-size: 14px !important;
        }
        
        .panda-search-btn-<?php echo $row_mod['md_id'] ?> {
            padding: 8px 12px !important;
            font-size: 12px !important;
            right: 6px !important;
        }
        
        .panda-keywords-<?php echo $row_mod['md_id'] ?> {
            margin-top: 20px;
            gap: 6px;
        }
        
        .keywords-label-<?php echo $row_mod['md_id'] ?> {
            font-size: 11px;
            padding: 6px 12px;
        }
        
        .keyword-tag-<?php echo $row_mod['md_id'] ?> {
            font-size: 12px;
            padding: 6px 12px;
        }
    }

    /* 더 작은 모바일 화면용 (갤럭시폰 등) */
    @media all and (max-width:400px) {
        
        .panda-search-container-<?php echo $row_mod['md_id'] ?> .panda-search-box-<?php echo $row_mod['md_id'] ?> {
            padding: 10px 70px 10px 16px !important;
            font-size: 13px !important;
        }
        
        .panda-search-btn-<?php echo $row_mod['md_id'] ?> {
            padding: 6px 8px !important;
            font-size: 11px !important;
            right: 4px !important;
        }
    }

    
</style>

<div class="rb_w100_div rb_w100_<?php echo $row_mod['md_id'] ?>">
    <ul style="width:<?php echo $rb_core['main_width'] ?>px;">
        <div class="panda-search-container-<?php echo $row_mod['md_id'] ?>">
            <!-- 검색창 -->
            <div class="panda-search-wrapper-<?php echo $row_mod['md_id'] ?>">
                <input type="text" class="panda-search-box-<?php echo $row_mod['md_id'] ?>" placeholder="어떤 서비스가 필요하세요?" id="panda_search_input_<?php echo $row_mod['md_id'] ?>" onkeypress="if(event.key==='Enter'){performPandaSearch_<?php echo $row_mod['md_id'] ?>()}">
                <button class="panda-search-btn-<?php echo $row_mod['md_id'] ?>" onclick="performPandaSearch_<?php echo $row_mod['md_id'] ?>()">검색</button>
            </div>
        </div>
    </ul>
</div>

<script>
        
        //부모 width를 무시하고 div 를 100%로 만들고, 모듈설정 버튼의 100% 처리를 위해 스크립트를 사용 합니다.
        //복제 사용을 위해 $row_mod['md_id'](모듈ID) 를 활용 합니다.
        
        function adjustDivWidth_<?php echo $row_mod['md_id'] ?>() {
            const content_w = $('.rb_w100_<?php echo $row_mod['md_id'] ?>');
            const firstAdminOv_w = content_w.nextUntil('.admin_ov').next('.admin_ov');
            
            if ($(window).width() > <?php echo $rb_core['main_width'] ?>) {
                content_w.css({
                    'width': '100vw',
                    'position': 'relative',
                    'left': '50%',
                    'transform': 'translateX(-50%)'
                });
                firstAdminOv_w.css({
                    'width': '100vw',
                    'left': '50%',
                    'transform': 'translateX(-50%)'
                });
            } else {
                content_w.css({
                    'width': '100%',
                    'position': 'static',
                    'left': '0',
                    'transform': 'none'
                });
                firstAdminOv_w.css({
                    'width': '100%',
                    'left': '0',
                    'transform': 'none'
                });
            }
        }
        
        // 판다랭크 스타일 검색 기능
        function performPandaSearch_<?php echo $row_mod['md_id'] ?>() {
            const searchInput = document.getElementById('panda_search_input_<?php echo $row_mod['md_id'] ?>');
            const searchTerm = searchInput.value.trim();
            
            if (searchTerm) {
                // 그누보드 검색 페이지로 이동 (필요에 따라 수정)
                window.location.href = '<?php echo G5_BBS_URL ?>/search.php?sfl=wr_subject&stx=' + encodeURIComponent(searchTerm);
            }
        }
        
        // 키워드 태그 클릭시 검색
        function searchPandaKeyword_<?php echo $row_mod['md_id'] ?>(keyword) {
            const searchInput = document.getElementById('panda_search_input_<?php echo $row_mod['md_id'] ?>');
            searchInput.value = keyword;
            performPandaSearch_<?php echo $row_mod['md_id'] ?>();
        }

        $(document).ready(adjustDivWidth_<?php echo $row_mod['md_id'] ?>);
        $(window).resize(adjustDivWidth_<?php echo $row_mod['md_id'] ?>);
    </script>
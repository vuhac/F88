// Powered By Damocles In 2020/04/29
// 輸出Laravel款式頁碼 laravel_page.js
function laravel_page(total_data_count=0, now_page=1, per_page=10){ // 篩選後的總資料筆數，當前頁碼，每頁顯示資料筆數
    var page_block = '<ul class="pagination" role="navigation">';
    var total_data_count = parseInt(total_data_count);
    var max_page = Math.ceil(total_data_count / per_page); // 最大頁數

    // 上一頁(判斷如果為第1頁，這邊則要變成不可點選)
    if( now_page == 1 ){
        page_block +=
            '<li class="page-item disabled" aria-disabled="true" aria-label="« Previous">' +
                '<span class="page-link" aria-hidden="true">Previous</span>' +
            '</li>';
    }
    else{
        page_block +=
            '<li id="prev" class="page-item">' +
                '<a class="page-link" href="" rel="prev" aria-label="« Previous">Previous</a>' +
            '</li>';
    }

    // 頁數
    if(max_page > 7){ // 如果總頁數大於5頁，變成只顯示前後2頁，中間變成...
        if(now_page < 5){ // 判斷當前頁數在5以內，輸出1~5，...，最後一頁
            for(j=1; j<=5; j++){
                if( j == now_page ){
                    page_block += '<li class="page-item active" aria-current="page"><span class="page-link">' + j + '</span></li>';
                }
                else{
                    page_block += '<li class="page-item fake_page_num"><a class="page-link fake_page" href="#">' + j + '</a></li>';
                }
            } // end for
            page_block += '<li class="page-item disabled" aria-current="page-link"><span class="page-link">...</span></li>';
            page_block += '<li class="page-item fake_page_num"><a class="page-link fake_page" href="#">' + max_page + '</a></li>';
        }
        else if( now_page >= (max_page-3) ){ // 判斷當前頁數在最大頁數-3以內(含)，輸出第一頁，...，最大頁數-4 ~ 最大頁數
            page_block += '<li class="page-item fake_page_num"><a class="page-link fake_page" href="#">1</a></li>';
            page_block += '<li class="page-item disabled" aria-current="page-link"><span class="page-link">...</span></li>';
            for(j=(max_page-4); j<=max_page; j++){
                if( j == now_page ){
                    page_block += '<li class="page-item active" aria-current="page"><span class="page-link">' + j + '</span></li>';
                }
                else{
                    page_block += '<li class="page-item fake_page_num"><a class="page-link fake_page" href="#">' + j + '</a></li>';
                }
            } // end for
        }
        else{ // 當前頁數超過6
            page_block += '<li class="page-item fake_page_num"><a class="page-link fake_page" href="#">1</a></li>';
            page_block += '<li class="page-item disabled" aria-current="page-link"><span class="page-link">...</span></li>';
            page_block += '<li class="page-item fake_page_num"><a class="page-link fake_page" href="#">' + (parseInt(now_page)-1) + '</a></li>';
            page_block += '<li class="page-item active" aria-current="page"><span class="page-link">' + now_page + '</span></li>';
            page_block += '<li class="page-item fake_page_num"><a class="page-link fake_page" href="#">' + (parseInt(now_page)+1) + '</a></li>';
            page_block += '<li class="page-item disabled" aria-current="page-link"><span class="page-link">...</span></li>';
            page_block += '<li class="page-item fake_page_num"><a class="page-link fake_page" href="#">' + max_page + '</a></li>';
        }
    }
    else{ // 總頁數<=7，頁數全部顯示
        for(j=1; j<=max_page; j++){
            if( j == now_page ){
                page_block += '<li class="page-item active" aria-current="page"><span class="page-link">' + j + '</span></li>';
            }
            else{
                page_block += '<li class="page-item fake_page_num"><a class="page-link fake_page" href="#">' + j + '</a></li>';
            }
        } // end for
    }

    // 下一頁(判斷如果為最後頁，這邊則要變成不可點選)
    if( now_page == max_page ){
        page_block +=
            '<li class="page-item disabled" aria-disabled="true" aria-label="Next »">' +
                '<span class="page-link" aria-hidden="true">Next</span>' +
            '</li>';
    }
    else{
        page_block +=
            '<li id="next" class="page-item">' +
                '<a class="page-link" href="#" rel="next" aria-label="Next »">Next</a>' +
            '</li>';
    }
    page_block += '</ul>';
    return page_block;
} // end laravel_page

/* // 原版應該長的樣子(置中)
    <div class="mt-3 d-flex justify-content-center">
        <ul id="pages" class="pagination" role="navigation">
            <li class="page-item disabled" aria-disabled="true" aria-label="« Previous"><span class="page-link" aria-hidden="true">‹</span></li>
            <li class="page-item active" aria-current="page"><span class="page-link">1</span></li>
            <li class="page-item disabled" aria-disabled="true" aria-label="Next »"><span class="page-link" aria-hidden="true">›</span></li>
        </ul>
    </div>
*/
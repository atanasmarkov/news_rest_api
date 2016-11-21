<?php

/**
 * News full texts.
 * Actually here we divide tables just to show how we can use a simple table for searches + big data one separately to
 * allow faster operations out of really heavy tables. For the news task actually we don't need it- the adate index for latest
 * + access by PK is enough until we have millions of news.
 * 
 * At first join will be very slow option. Lately we'll see big diffeneces as selects won't need to decompress rows with big blobs 
 * and InnoDB file pages will contain many records in news table instead of 1-2 each.
 * 
 * Lately select from the news table will be much faster when we fill it.
 */
class NewsTexts extends Model {
    const TABLE_NAME= 'news_texts';
    
    
}



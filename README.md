# wwwbot
A sophisticated web bot capable of crawling websites, fetching emails, phone numbers, auto-filling forms etc.

```php

    require_once 'wwwbot.php'; 

    $url = 'https://example.com';

    $formdata = [
       'email'    => 'admin@az.com',
       'password' => 'admin',
       'username' => 'example'
    ];

    $options = [
       'method'        => 'POST',  // POST or GET
       'find_emails'   => false,   // true or false (find emails)
       'depth'         => 1000,    // set the crawling depth for each link found
       'form_attempts' => 100  	   // number of form attempts
    ]; 

    $bot = new WWWBot( $url, $formdata, $options );

```

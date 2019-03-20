<?php
   
 class WWWBot
 {
    public $separator = '__FormBot__';
    public $FoundEmails = [];
    public $find_emails = false;
    public $autosubmit_form = false;
    public $return_form_results = true;
    public $form_attempts = 1;
    public $content;
    public $WebAddr;
    public $method = 'POST';
    public $timeout = 3600;
    public $max_redirects = 20;
    public $cookie = 'foo=bar';
    public $depth = 5;
    public $accept_language = 'en';
    public $user_agent = 'UA - Default Bot 2.0';
    public $result = null;
    public $run_endlessly = true;
    public $params = [];
    public $Data = [];

	public function __construct( $WebAddr , $Data = false,  $Options = false)
 	{   
 		  ini_set('max_execution_time', 0);   # setting this script execution time to unlimited
      ini_set('memory_limit', '1024M');   # allocationg 1GB of memory to this script

 		  $this->WebAddr = $WebAddr;
      static $seen = [];
      if (isset($seen[$WebAddr]) || $this->depth <= 0 ) : 
        return; 
      endif;

      $seen[$WebAddr] = true; 
      $this->depth = $this->depth-1;
    
      if ($Options and is_array($Options) ) { 

         foreach ($Options as $option => $value) {
            if (true) {
               $this->$option = $value;
            }
         } 			
      }

      $Data = !$Data ? ['POST_DATA' => false] : $Data;
      $Params = [

        'http'=> [ 

            'timeout'	      => $this->timeout,   # one hour 
            'method'        => $this->method, 
            'user_agent'    => $this->user_agent,
            'max_redirects' => $this->max_redirects,
            'header'        => "Content-type: application/x-www-form-urlencoded\r\n" .			   
                               "Accept-language: $this->accept_language \r\n".
                               "Cookie: $this->cookie \r\n". 
                               "Content-Type: text/html\r\n",
            /*'proxy'         => "tcp://221.176.14.72:80",*/
            'content'	      => http_build_query($Data),		
        ],
      ];

      $WebAddr = self::FormatURL($WebAddr);

      $Context = stream_context_create( $Params ); 
      $Content = @file_get_contents( $WebAddr , false , $Context ); 
      
      $this->params = $Params;
      $this->data = $Data;
      $this->content = $Content; 
      $this->result = [

        'body_text'  => self::GetTagName('body'),
        'title_text' => self::GetTagName('title'),
        'links_found'=> self::GetValByAttr( 'a' , 'href' , $WebAddr ),
      ];

      # Fetch all emails found for marketing
      if($this->find_emails) {
         self::FindEmails();  
      }

      # Autofill and submit any form found
      if ($this->autosubmit_form) {
        self::AutoSubmitForm();
      }
    
      if( $this->depth !=0 ) 
      {      
          $links = explode($this->separator , $this->result['links_found']);
          foreach ( $links as $key => $value ) 
          {
              if( !empty($value) )
              { 
                   // print $value; //new FormBot($value, $this->data, $this->params );
              }         
          }        
      }   
 	}

 	public function FindEmails () 
 	{

      $matches = [];
      $pattern = '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}/';
      preg_match_all($pattern, @strtolower($this->content), $matches);

      $emails = array_values(array_unique($matches[0]));

      $mailto = self::GetValByAttr('a' , 'href');
      foreach (explode($this->separator, $mailto) as $key => $value) {
          if (!empty($key) and substr($mailto[$key], 0, 7) == "mailto:") {

             $email = $mailto[$key];
             array_values($email); 
          }
      }
      $emails = array_filter($emails); # Removing empty emails
      $this->FoundEmails = implode($this->separator, $emails); 

      if( !empty($this->FoundEmails) and @count($this->FoundEmails) > 0 ) {
         # Display results for page containing emails found
         ?> 
         <h3>
           On the page: <a href="<?php print $this->WebAddr ?>" target="_blank"> <?php print $this->WebAddr ?></a>, I found these emails
         </h3>
         <?php

         foreach (explode($this->separator, $this->FoundEmails) as $key => $value ) {
             print $value . '<br>';
         }          
      }  
      else
      {
         print "<h3>Oops, I found no email on the page ". $this->WebAddr ." </h3>";
      }
 	}

	public function FindPhoneNumbers ( ) 
 	{	
	  $tel = self::GetValByAttr('a' , 'href');
 		foreach (explode($this->separator, $tel) as $key => $value) {

 			if (substr($value , 0, 4) == "tel:" ) {

 			   $v = substr($value, 4, strlen($value));
 			   $telephone = array_values([$v]);
 			   $this->FoundPhoneNumbers = implode($this->separator, $telephone);
 			}
 		} 
 	}

 	public function AutoSubmitForm ()
 	{
        $FormElements = self::GetTagName('form');

        foreach ( explode($this->separator , $FormElements) as $key => $value ) { 

        	$FormAction = self::GetValByAttr ( 'form' , 'action' ); 
        	$FormMethod = self::GetValByAttr ( 'form' , 'method' );
        	$InputNames = self::GetValByAttr ( 'input' , 'name' );

        	$FormData = [];

        	foreach (explode($this->separator , $InputNames) as $key => $value) {
        		 $FormData[$value]  = substr(md5(random_int(0, time() ) ), 0 , 10 ); # random sting of 10 bits
        	}	
          
          $FormAction = empty($FormAction) ? $this->WebAddr : $FormAction ; 

          foreach (explode($this->separator, $FormMethod) as $key => $value) { 

            for ($k=0; $k < $this->form_attempts; $k++) {

                $ch = curl_init($FormAction);

                curl_setopt($ch, CURLOPT_POST , true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $FormData);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Cookie: $this->cookie"]);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $Content = curl_exec($ch);
                curl_close($ch);  

                if($Content != false and count($FormAction) > 0 ) {

                   printf("Post sucessful, %d forms were found <br>" , count($FormAction));
                   printf("Attempt number %d", $k); 

                   if( $this->return_form_results ) {
                       //print $Content;
                       //print_r($Content); 
                   }                   
                }                  
            }
         }
      }
 	}

	public function ReFormatLink ( $link , $exception = false )
	{	 
 		
 		$url = $this->WebAddr; 
		/**
		 * Doing some stuff with '$link' , re-arranging links to best
		 * suit the website crawling process...This also avoid Bot from
		 * crawling duplicate content
		**/

		# ignore mailto: and tel:

		if( substr($link,0,4) == 'tel:' or substr($link,0,7) == 'mailto:' ) :
		   
       $link = ""; 
    
		# filter links with '/' e.g /privacy

		elseif( substr($link,0,1) == '/' &&  substr($link,0,2) != '//' ) :
		   $link = parse_url($url)['scheme'].'://'.parse_url($url)['host'].$link;

		# filter links with '//' e.g //privacy

		elseif ( substr($link,0,2) == '//' ) :
			$link = parse_url($url)['scheme'].':'.$link;
		 
		# filter links with './' e.g ./privacy

		elseif ( substr($link,0,2) == './' ) :
			$link = parse_url($url)['scheme'].'://'.parse_url($url)['host'].dirname(parse_url($url)['path']).substr($link , 1);

		# ignore empty links eg. ''

		elseif ( empty( $link ) ) :
			$link = '';

		# ignore links pointing back to homepage eg. '/' , 'http://siteantipas.com/index.php'

		elseif ( $link === '/' or $link === $WebsiteURL ) :
			$link = '';

		# ignore links with '#' e.g #privacy

		elseif ( substr($link,0,1) == '#' or strpos($link, '#') !== false ) :
			$link = '';

		# filter links with '../' e.g .../../privacy

		elseif ( substr($link,0,3) == '../' ) :
			$link = parse_url($url)['scheme'].'://'.parse_url($url)['host'].'/'.$link ;

		# ignore javascript links eg. 'javscript:privacy()'

		elseif ( substr($link,0,11) == 'javascript:' ) :
			$link = '';

		# filter links with relative path e.g 'privacy.php'

		elseif ( substr($link,0,5) != 'https' && substr($link,0,4) != 'http'  ) :
			@$link = @parse_url($url)['scheme'].'://'.parse_url($url)['host'].dirname(parse_url($url)['path']).'/'.$link ;

		endif;

	    return $link;
	}

	public function FormatURL ( $URL )
 	{	
 		# $URL =  remove_extra_spaces ($URL);
	    $URL =  preg_match('~^(?:f|ht)tps?://~i', $URL) == true ? $URL : 'http://' . $URL;  # adding 'http://' to url if not present
	    $URL =  implode('',   explode('www.', $URL ) ); 									# removing 'www.' from url if present
	    return $URL;
 	}

	public function GetValByAttr ( $TagName , $Attr )
 	{	
		 # Gets Webpage Tag's attribute's value
 		 # @example 	<img    src=      'my_image_src.jpg'

 		 $WebAddr = $this->WebAddr;

		 @$Document = new DOMDocument;
		 @$Document->loadHTML( $this->content );
		 @$Document->preserveWhiteSpace = false;

		 $AllTags = $Document->getElementsByTagName( $TagName );

		 foreach( $AllTags as $value ) :
		    @$Attributes  = $value->attributes; 

			if (in_array($Attr, ['url', 'source', 'href', 'action' , 'src']) ) :
 		        @$Attr_Values[] = self::ReFormatLink( @$Attributes->getNamedItem( $Attr )->nodeValue );
 			else :
 				@$Attr_Values[] = @$Attributes->getNamedItem( $Attr )->nodeValue;	
			endif;			    	    
		 endforeach;

		 $AllTagsAttrValue = @implode( $this->separator , $Attr_Values );

		 if ( !empty($AllTagsAttrValue) and @count($AllTagsAttrValue > 0 ) ) :

			 return $AllTagsAttrValue;
		 else :
		 	 return '';
		 endif;
 	} 

	public function GetTagName ( $TagName )
 	{

		# Gets Webpage Text
		@$Document = new DOMDocument;
		@$Document->loadHTML( $this->content ); 
		@$Document->preserveWhiteSpace = false;

		$script = $Document->getElementsByTagName('script');
		$style  = $Document->getElementsByTagName('style');

		# remove script tags from document
		for ($nodeIdx = $script->length; --$nodeIdx >= 0; ) {
		    $node = $script->item($nodeIdx);
		    $node->parentNode->removeChild($node);
		}

		# remove style tags from document
		for ($nodeIdx = $style->length; --$nodeIdx >= 0; ) {
		    $node = $style->item($nodeIdx);
		    $node->parentNode->removeChild($node);
		}

		 $TagText = $Document->getElementsByTagName($TagName);
		 
		 foreach ($TagText as $Text ) :	 	

		 	$TextArray[] =   $Text->nodeValue;
		 endforeach;
		 
		 if ( !empty($TextArray) ) :

			 return @implode( $this->separator , $TextArray );
		 else : 
		 	 return '';
		 endif;	     
 	} 

 	public function GetTagElement ( $TagName )
 	{

        # Gets Webpage Text
		@$Document = new DOMDocument;
		@$Document->loadHTML( $this->content ); 
		@$Document->preserveWhiteSpace = false;

		$AllTags = $Document->getElementsByTagName($TagName);
		return $AllTags;		
 	}		

}
  

?>

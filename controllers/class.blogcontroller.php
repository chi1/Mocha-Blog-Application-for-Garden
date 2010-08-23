<?php if (!defined('APPLICATION')) exit();
/*
Blog for Garden. By chi1.
*/

class BlogController extends Gdn_Controller {
 
   public function __construct() {
      parent::__construct();
   }
   
   public function Initialize() {
      if ($this->DeliveryType() == DELIVERY_TYPE_ALL)
         $this->Head = new HeadModule($this);
      parent::Initialize();
      
        $this->ShowOptions = TRUE;
        $this->Menu->HighlightRoute('/blog');
        $this->AddCssFile('style.css');
        $this->FireEvent('AfterInitialize');
   }
   
   public function Index($value='')
   {
	$BlogcategoryID = 1; // Edit this value
	$Postsperpage = 10; // Edit this value
   	
       $BlogModel = new BlogModel();
       $this->BlogData = $BlogModel->GetBlog('0', $Postsperpage, array('d.CategoryID' => $BlogcategoryID));

       $this->Render();
   }
}

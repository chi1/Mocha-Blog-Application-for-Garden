<?php if (!defined('APPLICATION')) exit();
/*
Blog for Garden. By chi1.
*/

/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

class BlogModel extends VanillaModel {
   /**
    * Class constructor.
    */
   public function __construct() {
      parent::__construct('Blog');
   }
   
   public function BlogSummaryQuery($AdditionalFields = array()) {
      $Perms = $this->CategoryPermissions();
      if($Perms !== TRUE) {
         $this->SQL->WhereIn('d.CategoryID', $Perms);
      }
      
      $this->SQL
         ->Select('d.InsertUserID', '', 'FirstUserID')
         ->Select('d.DateInserted', '', 'FirstDate')
			->Select('d.CountBookmarks')
         ->Select('iu.Name', '', 'FirstName') // <-- Need these for rss!
         ->Select('iu.Photo', '', 'FirstPhoto')
         ->Select('d.Body') // <-- Need these for rss!
         ->Select('d.Format') // <-- Need these for rss!
         ->Select('d.DateLastComment', '', 'LastDate')
         ->Select('d.LastCommentUserID', '', 'LastUserID')
         ->Select('lcu.Name', '', 'LastName')
         ->Select("' &rarr; ', pc.Name, ca.Name", 'concat_ws', 'Category')
         ->Select('ca.UrlCode', '', 'CategoryUrlCode')
         ->From('Discussion d')
         ->Join('User iu', 'd.InsertUserID = iu.UserID', 'left') // First comment author is also the discussion insertuserid
         ->Join('User lcu', 'd.LastCommentUserID = lcu.UserID', 'left') // Last comment user
         ->Join('Category ca', 'd.CategoryID = ca.CategoryID', 'left') // Category
         ->Join('Category pc', 'ca.ParentCategoryID = pc.CategoryID', 'left'); // Parent category
			
		if(is_array($AdditionalFields)) {
			foreach($AdditionalFields as $Alias => $Field) {
				// See if a new table needs to be joined to the query.
				$TableAlias = explode('.', $Field);
				$TableAlias = $TableAlias[0];
				if(array_key_exists($TableAlias, $Tables)) {
					$Join = $Tables[$TableAlias];
					$this->SQL->Join($Join[0], $Join[1]);
					unset($Tables[$TableAlias]);
				}
				
				// Select the field.
				$this->SQL->Select($Field, '', is_numeric($Alias) ? '' : $Alias);
			}
		}
         
      $this->FireEvent('AfterDiscussionSummaryQuery');
   }
   
   public function GetBlog($Offset = '0', $Limit = '', $Wheres = '', $AdditionalFields = NULL) {
      if ($Limit == '') 
         $Limit = Gdn::Config('Blog.Blogposts.PerPage', 5);

      $Offset = !is_numeric($Offset) || $Offset < 0 ? 0 : $Offset;
      
      $Session = Gdn::Session();
      $UserID = $Session->UserID > 0 ? $Session->UserID : 0;
      $this->BlogSummaryQuery();
      $this->SQL
         ->Select('d.*');
         
      if ($UserID > 0) {
         $this->SQL
            ->Select('w.UserID', '', 'WatchUserID')
            ->Select('w.DateLastViewed, w.Dismissed, w.Bookmarked')
            ->Select('w.CountComments', '', 'CountCommentWatch')
            ->Join('UserDiscussion w', 'd.DiscussionID = w.DiscussionID and w.UserID = '.$UserID, 'left');
      } else {
			$this->SQL
				->Select('0', '', 'WatchUserID')
				->Select('now()', '', 'DateLastViewed')
				->Select('0', '', 'Dismissed')
				->Select('0', '', 'Bookmarked')
				->Select('0', '', 'CountCommentWatch')
				->Select('d.Announce','','IsAnnounce');
      }
		
		$this->AddArchiveWhere($this->SQL);
      
      if (is_array($Wheres))
         $this->SQL->Where($Wheres);
			
		// If not looking at discussions filtered by bookmarks or user, filter announcements out.
		if (!isset($Wheres['w.Bookmarked']) && !isset($Wheres['d.InsertUserID']))
			$this->SQL->Where('d.Announce<>', '1');
			
		$this->FireEvent('BeforeGet');
      
      $Data = $this->SQL
         ->OrderBy('d.DateInserted', 'desc')
         ->Limit($Limit, $Offset)
         ->Get();
			
		$this->AddBlogColumns($Data);
		
		return $Data;
   }
	
	public function AddBlogColumns($Data) {
		// Change discussions based on archiving.
		$ArchiveTimestamp = Gdn_Format::ToTimestamp(Gdn::Config('Vanilla.Archive.Date', 0));
		$Result = &$Data->Result();
		foreach($Result as &$Discussion) {
			if(Gdn_Format::ToTimestamp($Discussion->DateLastComment) <= $ArchiveTimestamp) {
				$Discussion->Closed = '1';
				if($Discussion->CountCommentWatch) {
					$Discussion->CountUnreadComments = $Discussion->CountComments - $Discussion->CountCommentWatch;
				} else {
					$Discussion->CountUnreadComments = 0;
				}
			} else {
				$Discussion->CountUnreadComments = $Discussion->CountComments - $Discussion->CountCommentWatch;
			}
		}
	}
	
	/**
	 * @param Gdn_SQLDriver $Sql
	 */
	public function AddArchiveWhere($Sql = NULL) {
		if(is_null($Sql))
			$Sql = $this->SQL;
		
		$Exclude = Gdn::Config('Vanilla.Archive.Exclude');
		if($Exclude) {
			$ArchiveDate = Gdn::Config('Vanilla.Archive.Date');
			if($ArchiveDate) {
				$Sql->Where('d.DateLastComment >', $ArchiveDate);
			}
		}
	}
   
   // Returns all users who have bookmarked the specified discussion
   public function GetBookmarkUsers($DiscussionID) {
      return $this->SQL
         ->Select('UserID')
         ->From('UserDiscussion')
         ->Where('DiscussionID', $DiscussionID)
         ->Where('Bookmarked', '1')
         ->Get();
   }
   
   protected $_CategoryPermissions = NULL;
   
   public function CategoryPermissions($Escape = FALSE) {
      if(is_null($this->_CategoryPermissions)) {
         $Session = Gdn::Session();
         
         if((is_object($Session->User) && $Session->User->Admin == '1')) {
            $this->_CategoryPermissions = TRUE;
			} elseif(C('Garden.Permissions.Disabled.Category')) {
				if($Session->CheckPermission('Vanilla.Discussions.View'))
					$this->_CategoryPermissions = TRUE;
				else
					$this->_CategoryPermissions = array(); // no permission
         } else {
            $Data = $this->SQL
               ->Select('c.CategoryID')
               ->From('Category c')
               ->Permission('Vanilla.Discussions.View', 'c', 'CategoryID')
               ->Get();
            
            $Data = $Data->ResultArray();
            $this->_CategoryPermissions = array();
            foreach($Data as $Row) {
               $this->_CategoryPermissions[] = ($Escape ? '@' : '').$Row['CategoryID'];
            }
         }
      }
      
      return $this->_CategoryPermissions;
   }

   public function GetCount($Wheres = '', $ForceNoAnnouncements = FALSE) {
      $Session = Gdn::Session();
      $UserID = $Session->UserID > 0 ? $Session->UserID : 0;
      if (is_array($Wheres) && count($Wheres) == 0)
         $Wheres = '';
         
      $Perms = $this->CategoryPermissions();
      if($Perms !== TRUE) {
         $this->SQL->WhereIn('c.CategoryID', $Perms);
      }
         
      // Small optimization for basic queries
      if ($Wheres == '') {
         $this->SQL
            ->Select('c.CountDiscussions', 'sum', 'CountDiscussions')
            ->From('Category c');
      } else {
         $this->SQL
	         ->Select('d.DiscussionID', 'count', 'CountDiscussions')
	         ->From('Discussion d')
            ->Join('Category c', 'd.CategoryID = c.CategoryID')
	         ->Join('UserDiscussion w', 'd.DiscussionID = w.DiscussionID and w.UserID = '.$UserID, 'left')
            ->Where($Wheres);
      }
      return $this->SQL
         ->Get()
         ->FirstRow()
         ->CountDiscussions;
   }

   public function GetID($DiscussionID) {
      $Session = Gdn::Session();
      $this->FireEvent('BeforeGetID');
      $Data = $this->SQL
         ->Select('d.*')
         ->Select('ca.Name', '', 'Category')
         ->Select('ca.UrlCode', '', 'CategoryUrlCode')
         ->Select('w.DateLastViewed, w.Dismissed, w.Bookmarked')
         ->Select('w.CountComments', '', 'CountCommentWatch')
         ->Select('d.DateLastComment', '', 'LastDate')
         ->Select('d.LastCommentUserID', '', 'LastUserID')
         ->Select('lcu.Name', '', 'LastName')
			->Select('iu.Name', '', 'InsertName')
			->Select('iu.Photo', '', 'InsertPhoto')
         ->From('Discussion d')
         ->Join('Category ca', 'd.CategoryID = ca.CategoryID', 'left')
         ->Join('UserDiscussion w', 'd.DiscussionID = w.DiscussionID and w.UserID = '.$Session->UserID, 'left')
			->Join('User iu', 'd.InsertUserID = iu.UserID', 'left') // Insert user
			->Join('Comment lc', 'd.LastCommentID = lc.CommentID', 'left') // Last comment
         ->Join('User lcu', 'lc.InsertUserID = lcu.UserID', 'left') // Last comment user
         ->Where('d.DiscussionID', $DiscussionID)
         ->Get()
         ->FirstRow();
		
		if (
			$Data
			&& Gdn_Format::ToTimestamp($Data->DateLastComment) <= Gdn_Format::ToTimestamp(Gdn::Config('Vanilla.Archive.Date', 0))
		) {
			$Data->Closed = '1';
		}
		return $Data;
   }
  }

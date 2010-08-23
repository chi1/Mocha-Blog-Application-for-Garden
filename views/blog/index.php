<ul class="DataList Blog">
<?php foreach ($this->BlogData->Result() as $Blogpost): ?>
<li class="Item">
   <div class="ItemContent Blogpost">
   	<?php echo Anchor(Gdn_Format::Text($Blogpost->Name), '/discussion/'.$Blogpost->DiscussionID, 'Title'); ?>
   	<div class="Message Blogbody">
   		<p><?php echo Gdn_Format::To($Blogpost->Body, $Blogpost->Format); ?></p>
   	</div>
   	<div class="Meta">
         <?php if ($Blogpost->Closed == '1') { ?>
         <span class="Closed"><?php echo T('Closed'); ?></span>
         <?php } ?>

            <?php 
            $count = $Blogpost->CountComments - 1;
            $text = ($count > 0) ? $count . ' comments' : 'No comments.';

            echo '<span class="CommentCount">'.Anchor($text, '/discussion/'.$Blogpost->DiscussionID).'</span>';
            echo '<span class="LastCommentDate">'.Gdn_Format::Date($Blogpost->FirstDate).'</span>'; // Should maybe use another css class?
         
               
         ?>
      </div>
</li>
<?php endforeach; ?>
</ul>

<?php 

echo Wrap(Anchor('More posts', '/categories/'.$BlogcategoryID, 'More')); ?>

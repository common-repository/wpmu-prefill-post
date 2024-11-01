<?php
/*
Plugin Name: WPMU Prefill Post
Plugin URI: http://wordpress.org/extend/plugins/wpmu-prefill-post/
Description: Add the ability to create post template. Work with wordpress and wordpress mu. Works with qtranslate. Inspirated from "Article Templates". Note : You need php 5.2+
Author: Benjamin Santalucia (ben@woow-fr.com)
Version: 1.02
Author URI: http://wordpress.org/extend/plugins/profile/ido8p
*/

if (!class_exists('WPMUPrefillPost')) {
	class WPMUPrefillPost{
		const ADMINISTRATOR = 8;
		const DOMAIN = 'WPMUPrefillPost';
		const MASTERBLOG = 1;
		public function WPMUPrefillPost(){
			$this->plugin_name = plugin_basename(__FILE__);
			if ( is_admin() ) {
				// Start this plugin once all other plugins are fully loaded
				add_action( 'plugins_loaded', array(&$this, 'start_plugin') );
				add_action('admin_menu',array (&$this, 'add_admin_menu'));

				add_action('admin_init', array(&$this,'editorAdminInit'));
				//add_action('admin_head', array(&$this,'editorAdminHead'));
			}
			register_activation_hook( $this->plugin_name, array(&$this,'activate'));
			register_uninstall_hook( $this->plugin_name, array('WPMUPrefillPost', 'uninstall') );
		}
		public function start_plugin(){
			load_plugin_textdomain(self::DOMAIN, false, dirname($this->plugin_name).'/languages');
		}
		public static function getUrl(){
			return $PHP_SELF.'?page='.self::DOMAIN;
		}
		public static function uninstall(){
			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
			if ( is_multisite() ) {
				$current_blog = $wpdb->blogid;
				// Get all blog ids
				$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
				foreach ($blogids as $blog_id) {
					switch_to_blog($blog_id);
					self::dropDatabase();
				}
				switch_to_blog($current_blog);
				return;
			}
			self::dropDatabase();
		}
		public static function dropDatabase() {
			global $wpdb;
			$table_name= $wpdb->prefix.self::DOMAIN;
			$sql = "DROP TABLE $table_name;";
			$wpdb->query($sql);
		}
		public static function activate(){
			global $wpdb;

			if ( is_multisite() ) {
				// check if it is a network activation - if so, run the activation function for each blog id
				if (isset($_GET['networkwide']) && ($_GET['networkwide'] == 1)) {
					$current_blog = $wpdb->blogid;
					// Get all blog ids
					$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
					foreach ($blogids as $blog_id) {
						switch_to_blog($blog_id);
						self::install();
					}
					switch_to_blog($current_blog);
					return;
				}
			}
			self::install();
		}
		public static function install(){
			global $wpdb;

			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');

			$table_name= $wpdb->prefix.WPMUPrefillPost::DOMAIN;

			if( !$wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) ) {
				$sql = "CREATE TABLE $table_name(
						ID bigint(20) unsigned NOT NULL auto_increment,
						post_author bigint(20) unsigned NOT NULL default '0',
						post_content longtext NOT NULL,
						post_title text NOT NULL,
						post_excerpt text NOT NULL,
						PRIMARY KEY  (ID),
						UNIQUE post_title(`post_title`(255)),
						KEY post_author (post_author)
						) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
				$wpdb->query($sql);
			}
		}
		public function add_admin_menu(){
			self::activate();
			if (function_exists('add_options_page')) {
				add_options_page(__('WPMU Prefil Post Templates',self::DOMAIN), __('WPMU Prefil Post Templates',self::DOMAIN), self::ADMINISTRATOR, self::DOMAIN, array (&$this, 'showAdminMenu'));
			}
			if( function_exists('add_meta_box') ){
				add_meta_box(self::DOMAIN.'_meta', __('Templates',self::DOMAIN), array (&$this, 'showMetaBox'), 'post', 'side', 'high' );
			}
		}
		public function showMessage($class, $text){
			?>
			<div id="message" class="<? echo $class; ?> fade">
				<strong><?php echo $text; ?></strong>
			</div>
			<?php
		}
		public function getElements($orderBy="ID",$order="desc", $master=false){
			global $wpdb;
			$current_blog = $wpdb->blogid;
			if($master && $current_blog != self::MASTERBLOG) {
				switch_to_blog(self::MASTERBLOG);
			}else if($master && $current_blog == self::MASTERBLOG) {
				return array();
			}

			$table_name= $wpdb->prefix.self::DOMAIN;
			$query = "select * from $table_name order by ".$orderBy." ".$order;
			$lists = $wpdb->get_results($query);
			if($master) {
				restore_current_blog();
			}
			return $lists;
		}
		public function getElement($id){
			global $wpdb;
			$table_name= $wpdb->prefix.self::DOMAIN;
			$query = "select * from $table_name where id=$id";
			return $wpdb->get_row($query);
		}
		public function deleteElement($id){
			global $wpdb;
			require_once(ABSPATH . 'wp-admin/upgrade-functions.php');

			$table_name= $wpdb->prefix.self::DOMAIN;
			$sql = "DELETE FROM $table_name where id = $id;";
			$wpdb->query($sql);
		}
		/******************/
		public function updateTemplate($id,$post_title,$post_content,$post_excerpt){
			global $wpdb;
			/*if(get_magic_quotes_gpc()) {
				$post_title = stripslashes($post_title);
				$post_content = stripslashes($post_content);
				$post_excerpt = stripslashes($post_excerpt);
			}*/
			$table_name= $wpdb->prefix.self::DOMAIN;
			return $wpdb->update($table_name, array('post_title' => /*addslashes*/($post_title),
													'post_content' => /*addslashes*/($post_content),
													'post_excerpt' => /*addslashes*/($post_excerpt)
											), array('ID' => $id),array('%s','%s','%s'), '%d');

		}
		public function addTemplate($post_title,$post_content,$post_excerpt){
			global $wpdb, $user_ID;
			/*if(get_magic_quotes_gpc()) {
				$post_title = stripslashes($post_title);
				$post_content = stripslashes($post_content);
				$post_excerpt = stripslashes($post_excerpt);
			}*/
			if ( is_multisite() ) {
				switch_to_blog(self::MASTERBLOG);
				$table_name= $wpdb->prefix.self::DOMAIN;
				restore_current_blog();
				echo 'select id from '.$table_name.' where post_title ="'./*addslashes*/($post_title).'"';
				if($wpdb->get_var( 'select id from '.$table_name.' where post_title ="'./*addslashes*/($post_title).'"')) {
					return false;
				}
			}
			$table_name= $wpdb->prefix.self::DOMAIN;

			return $wpdb->insert($table_name, array('post_title' => /*addslashes*/($post_title),
													'post_content' => /*addslashes*/($post_content),
													'post_excerpt' => /*addslashes*/($post_excerpt),
													'post_author' => $user_ID
											), array('%s','%s','%s','%d'));
		}
		public function showMetaBox() {	?>
			<select id="<?php echo WPMUPrefillPost::DOMAIN; ?>Select" style="width:100%">
			<?php
			global $wpdb;
			$elements = $this->getElements();
			$exclude = array();
			$templates = array();
			$titles = array();
			$current_blog = $wpdb->blogid;
			if (is_multisite() && $current_blog != self::MASTERBLOG) {
				$masterElements = $this->getElements('ID','desc', true);
				$this->showMetaBoxOption($masterElements, $exclude, self::MASTERBLOG);

				foreach($masterElements as $element){
					$templates[self::MASTERBLOG.'-'.$element->ID]=stripslashes($element->post_content);
					$titles[self::MASTERBLOG.'-'.$element->ID]=stripslashes($element->post_title);
					$excerpt[self::MASTERBLOG.'-'.$element->ID]=stripslashes($element->post_excerpt);
				}
			}
			$this->showMetaBoxOption($elements, $exclude, $current_blog);
			foreach($elements as $element){
				$templates[$current_blog.'-'.$element->ID]=stripslashes($element->post_content);
				$titles[$current_blog.'-'.$element->ID]=stripslashes($element->post_title);
				$excerpt[$current_blog.'-'.$element->ID]=stripslashes($element->post_excerpt);
			}

			if(is_multisite() && $current_blog == self::MASTERBLOG) {
				$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
				foreach ($blogids as $blog_id) {
					if($blog_id == $current_blog){
						continue;
					}
					switch_to_blog($blog_id);
					$elements = $this->getElements('ID','desc', false);
					$this->showMetaBoxOption($elements, $exclude, $blog_id);
					foreach($elements as $element){
						$templates[$blog_id.'-'.$element->ID]=stripslashes($element->post_content);
						$titles[$blog_id.'-'.$element->ID]=stripslashes($element->post_title);
						$excerpt[$blog_id.'-'.$element->ID]=stripslashes($element->post_excerpt);
					}
				}
				switch_to_blog($current_blog);
			}
			?>
			</select>
			<input type="button" onclick="setTemplate();" name="button" value="<?php _e('Insert',WPMUPrefillPost::DOMAIN);?>" />
			<script type="text/javascript">
			var <?php echo self::DOMAIN;?>Templates = <?php echo json_encode($templates); ?>;
			var <?php echo self::DOMAIN;?>Titles = <?php echo json_encode($titles); ?>;
			var <?php echo self::DOMAIN;?>Excerpt = <?php echo json_encode($excerpt); ?>;
			function setTemplate() {
				var select = document.getElementById("<?php echo WPMUPrefillPost::DOMAIN; ?>Select");
				var content = <?php echo self::DOMAIN;?>Templates[select.value];
				if(!content) return;

				//content
				if(window.tinyMCE && document.getElementById("content").style.display=="none") {
					if(hasContent()) {
						alert("<?php _e("Clear the post content before inserting template",WPMUPrefillPost::DOMAIN);?>");
						return;
					}
					tinyMCE.get('content').setContent(content/*.replace(/\n/g,"<br />")*/);

				} else if(document.getElementById("content")) {
					if(hasContent()) {
						alert("<?php _e("Clear the post content before inserting template",WPMUPrefillPost::DOMAIN);?>");
						return;
					}
					document.getElementById("content").value = content;
				}
				//title
				var tpt = document.getElementById('title-prompt-text');
				if(tpt){
					tpt.style.visibility="hidden";
				}
				document.getElementById('title').value=<?php echo self::DOMAIN;?>Titles[select.value];

				//excerpt
				var texcerpt = document.getElementById('excerpt');
				if(texcerpt){
					texcerpt.value=<?php echo self::DOMAIN;?>Excerpt[select.value];
				}

				//if qtrans is enabled
				if(typeof qtrans_get_active_language == "function"){
					var lng = qtrans_get_active_language();
					switchEditors.go('content', lng);
					qtrans_assign('qtrans_textarea_content',qtrans_use(lng,content));

					var titles =jQuery(".qtrans_title_input");
					for (var i=0; i<titles.length; i++){
						var lng = titles[i].id.match(/[a-z][a-z]$/i)[0];
						titles[i].value = qtrans_use(lng,<?php echo self::DOMAIN;?>Titles[select.value]);
					}
					qtrans_integrate_title();
				}


			}
			function hasContent(){
				if(window.tinyMCE && tinyMCE.get('content'))
					return tinyMCE.get('content').getContent().replace(/<[^>]+>/g,'').replace(/\s/g,'').length>0;
				return document.getElementById("content").value.replace(/<[^>]+>/g,'').replace(/\s/g,'').length>0;
			}
			</script>
			<?php
		}
		public function showMetaBoxOption($elements, &$exclude, $blog_id){
			foreach($elements as $element){
				if(in_array($element->post_title,$exclude)) continue;
				?>
				<option value="<?php echo $blog_id;?>-<?php echo $element->ID; ?>"><?php echo stripslashes($element->post_title); ?></option>
				<?php
				$exclude[] = $element->post_title;
			}
		}
		public function showAdminMenu(){
			global $wpdb;
			isset($_GET['acc']) ? $_acc=$_GET['acc']:$_acc="showTemplates";

			if(!empty($_POST['title'])){
				if(!empty($_POST['id'])){
					if($this->updateTemplate($_POST['id'],$_POST['title'],$_POST['content'],$_POST['excerpt']))
						$this->showMessage('updated', __('Template correctly updated!',WPMUPrefillPost::DOMAIN));
					$_acc="showTemplates";
				}else{
					if($this->addTemplate($_POST['title'],$_POST['content'],$_POST['excerpt']))
						$this->showMessage('updated', __('Template correctly added!',WPMUPrefillPost::DOMAIN));
					else
						$this->showMessage('error', __('ERROR! This template is in database!',WPMUPrefillPost::DOMAIN));
					$_acc="addTemplate";
				}
			}else{
				if($_GET['acc']=="del") {
					if(!empty($_GET['id'])){
						$this->deleteElement($_GET['id']);
						$this->showMessage('updated', __('Tepmplate correctly deleted!',WPMUPrefillPost::DOMAIN));
					}
					$_acc="showTemplates";
				}
			}

			?>
			<link rel="stylesheet" type="text/css" href="<?php echo WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__)).'style.css';?>" />
			<script type="text/javascript">
			function deleteElement(id){
				var opc = confirm("<?php _e('You are going to delete this template, are you sure?',WPMUPrefillPost::DOMAIN); ?>");
				if (opc==true)
					window.location.href="<?php echo WPMUPrefillPost::getUrl(); ?>&acc=del&id="+id;
			}
			</script>
			<div class="wrap">
				<h2><?php _e('WPMU Prefill Post',WPMUPrefillPost::DOMAIN); ?></h2>

				<ul class="subsubsub">
					<li><a href="<?php echo WPMUPrefillPost::getUrl(); ?>&acc=showTemplates"><?php _e('Templates',WPMUPrefillPost::DOMAIN); ?></a> |</li>
					<li><a href="<?php echo WPMUPrefillPost::getUrl(); ?>&acc=addTemplate"><?php _e('Add templates',WPMUPrefillPost::DOMAIN); ?></a></li>
				</ul>
			<?php
				switch ($_acc){
					case "addTemplate":
						$this->showForm();
						break;
					case "edit":
						$id = $_GET['id'];
						$element = $this->getElement($id);
						$this->showForm($element->ID,$element->post_content,$element->post_title,$element->post_excerpt);
						break;
					case "showTemplates":
						if(empty($_GET['orderBy']))
							$_GET['orderBy'] = "id";
						if(empty($_GET['order']))
							$_GET['order'] = "desc";
						$elements = $this->getElements($_GET['orderBy'], $_GET['order']);

						$exclude = array();
						$current_blog = $wpdb->blogid;
						if (is_multisite() && $current_blog != self::MASTERBLOG) {
							$masterElements = $this->getElements($_GET['orderBy'], $_GET['order'], true);
							$this->showTemplates($masterElements, false, __('Templates on master blog',WPMUPrefillPost::DOMAIN), $exclude);
						}
						$this->showTemplates($elements, true, __('Templates',WPMUPrefillPost::DOMAIN), $exclude);


						if(is_multisite() && $current_blog == self::MASTERBLOG) {
							$blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
							foreach ($blogids as $blog_id) {
								if($blog_id == $current_blog){
									continue;
								}
								switch_to_blog($blog_id);
								$elements = $this->getElements($_GET['orderBy'], $_GET['order'], false);
								$this->showTemplates($elements, false, __('Templates on ',WPMUPrefillPost::DOMAIN).get_bloginfo('name'), $exclude);
							}
							switch_to_blog($current_blog);
						}
						break;
				}
			?>
			</div>
			<?php
		}
		public function showTemplates($elements, $editable = false, $title, &$exclude = array()){?>
			<h3><?php echo $title; ?></h3>
			<table class="widefat">
				<thead>
					<tr>
						<th scope="col">
							<?php _e('Title :',WPMUPrefillPost::DOMAIN); ?> (<a href="<?php echo WPMUPrefillPost::getUrl();?>&acc=showTemplates&orderBy=post_title&order=asc">+</a>|<a href="<?php echo WPMUPrefillPost::getUrl();?>&acc=showTemplates&orderBy=post_title&order=desc">-</a>)
						</th>
						<th scope="col">
							<?php _e('Excerpt :',WPMUPrefillPost::DOMAIN); ?> (<a href="<?php echo WPMUPrefillPost::getUrl();?>&acc=showTemplates&orderBy=post_excerpt&order=asc">+</a>|<a href="<?php echo WPMUPrefillPost::getUrl();?>&acc=showTemplates&orderBy=post_excerpt&order=desc">-</a>)
						</th>
						<th scope="col">
							<?php _e('Author :',WPMUPrefillPost::DOMAIN); ?>
						</th>
						<th scope="col"><?php _e('Delete',WPMUPrefillPost::DOMAIN); ?></th>
						<th scope="col"><?php _e('Edit',WPMUPrefillPost::DOMAIN); ?></th>
					</tr>
				</thead>
				<tbody id="the-comment-list" class="list:comment">
					<?php foreach($elements as $element){ ?>
						<tr id="element-<?php echo $element->id; ?>" class="<?php if(in_array($element->post_title,$exclude)) {echo "disabled";}?>">
							<td><?php echo stripslashes($element->post_title); ?></td>
							<td><?php echo stripslashes($element->post_excerpt); ?></td>
							<td><?php $user_info = get_userdata($element->post_author); echo $user_info->nickname; ?></td>
							<td>
								<?php if($editable){?>
								<a href="javascript:deleteElement(<?php echo $element->ID; ?>);"><?php _e('Delete',WPMUPrefillPost::DOMAIN); ?></a>
								<?php } ?>
							</td>
							<td>
								<?php if($editable){?>
								<a href="<?php echo WPMUPrefillPost::getUrl();?>&acc=edit&id=<?php echo $element->ID;?>"><?php _e('Edit',WPMUPrefillPost::DOMAIN); ?></a>
								<?php } ?>
							</td>
						</tr>
					<?php
							$exclude[] = $element->post_title;
						} ?>
				</tbody>
			</table>
		<?php
		}
		public function editorAdminInit(){
			wp_enqueue_script('word-count');
			wp_enqueue_script('post');
			wp_enqueue_script('editor');
			wp_enqueue_script('media-upload');
			wp_enqueue_script('common');
			wp_enqueue_script('utils');
		}
		/*public function editorAdminHead(){
			wp_tiny_mce();
		}*/
		public function showForm($id=null, $content='', $title='', $excerpt=''){
			?>
			<div id="poststuff">
			<h3><?php if (empty($id)) { _e('New template',WPMUPrefillPost::DOMAIN); } else { _e('Edit template',WPMUPrefillPost::DOMAIN); } ?></h3>
			<form method="post" action ="">
				<div id="post-body-content">
					<div id="titlediv">
						<h3 class="hndle"><span><?php _e('Title :',WPMUPrefillPost::DOMAIN); ?></span></h3>
						<div id="titlewrap">
							<input id="title" type="text" name="title" value="<?php echo str_replace('"','&#x22;',stripslashes($title)); ?>"/>
						</div>
						<div id="edit-slug-box"></div>
					</div>

					<div id="<?php echo user_can_richedit() ? 'postdivrich' : 'postdiv'; ?>" class="postarea">
						<div class="postbox">
							<h3 class="hndle"><span><?php _e('Content :',WPMUPrefillPost::DOMAIN); ?></span></h3>
							<div class="inside">
								<?php wp_editor(stripslashes(preg_replace(array('/\\\n+/','/\\\r+/'), array("",""),$content)),"content"); ?>
							</div>
						</div>
					</div>
					<div class="postbox">
						<h3 class="hndle"><span><?php _e('Excerpt :',WPMUPrefillPost::DOMAIN); ?></span></h3>
						<div class="inside">
							<textarea id="<?php echo WPMUPrefillPost::DOMAIN; ?>Excerpt" type="text" name="excerpt"><?php echo stripslashes($excerpt); ?></textarea>
						</div>
					</div>
				</div>
				<input type="hidden" name="id" value="<?php echo $id; ?>"/>
				<input type="submit" name="submit" value="<?php if (empty($id)) {_e('Add',WPMUPrefillPost::DOMAIN);}else{_e('Update',WPMUPrefillPost::DOMAIN);} ?>" /></p>
			</form>
			</div>
		<?php
			do_action('edit_page_form');
		}
	}
	$_WPMUPrefillPost = new WPMUPrefillPost();
}
?>

<?php
/**
 * Functions for handling releases, adding reviews to releases, etc.
 *
 * @package Ribcage
 * @subpackage Administration
 **/

/**
 * Manage releases panel - sends you out to add releases, remove releases, add reviews (and eventually add tracks).
 *
 * @return void
 */
function ribcage_manage_releases() {
	global $release, $releases, $artist, $tracks;
	
	$total_downloads = 0;

	$nonce = wp_create_nonce ('ribcage_manage_releases');

    if (isset($_REQUEST['release'])) {
        switch($_REQUEST['ribcage_action']) {
            case 'stats':
                ribcage_release_stats();
				return;
            break;

            case 'edit': 
            	check_admin_referer('ribcage_manage_releases');
            ?>
			<div class="wrap">
				<div id="icon-options-general" class="icon32"><br /></div>
				<?php
                $release = get_release($_REQUEST['release']);
				$artist = get_artist($release['release_artist']);
				$tracks = $release['release_tracks'];?>
				<h2>Editing <?php release_title(); ?></h2>
				<?php ribcage_release_form(); ?>
				<?php ribcage_tracks_form(); ?>
			</div> <?php	
				return;
            break;

            case 'reviews':
            	check_admin_referer('ribcage_manage_releases');
                ribcage_manage_reviews();
				return;
            break;

            case 'delete':
            	check_admin_referer('ribcage_manage_releases');
                delete_release($_REQUEST['release']);
				$message = " deleted";
            break;
        }
    }
	
	if (isset($message)){
		echo '<div id="message" class="updated fade"><p><strong>Release '.$message.'.</strong></p></div>';
		}
		
	register_column_headers('ribcage-manage-releases',
	array (
		'cb'=>'<input type="checkbox" />',
		'release_image' => '',
		'release_title'=> 'Release',
		'release_date'=> 'Release Date',
		'local_downloads'=>'Local Downloads',
		'remote_downloads'=>'Remote Downloads',
		'total_downloads'=>'Total Downloads'
		)
		);
		
	$releases = list_recent_releases_blurb();
	?>
	<div class="wrap">
			<div id="icon-options-general" class="icon32"><br /></div>
			<h2>Manage Releases</h2>
			<form action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>" method="post" id="ribcage_edit_artist" name="edit_artist">
				<table class="widefat post fixed" cellspacing="0">
						<thead>
						<tr>
						<?php print_column_headers('ribcage-manage-releases'); ?>			
						</tr>
						</thead>
						<tfoot>
						<tr>			
						<?php print_column_headers('ribcage-manage-releases',FALSE); ?>	
						</tr>
						</tfoot>            
						<tbody>
							<?php while ( have_releases () ) : the_release(); ?>
							<?php $artist = get_artist($release['release_artist']); ?>
							<?php echo ($alt % 2) ? '<tr valign="top" class="">' : '<tr valign="top" class="alternate">'; ++$alt; ?>		
							<th scope="row" class="check-column"><input type="checkbox" name="artistcheck[]" value="2" /></th>
							<td class="column-icon"><img src="<?php release_cover_tiny ();?>" height="65px" width="65px" alt="<?php release_title(); ?>" /></td>
                                                        <td class="column-name"><strong><a class="row-title" href="?page=manage_releases&release=<?php artist_id(); ?>" title="<?php artist_name(); ?>" ><?php artist_name(); ?> - <?php release_title(); ?></strong></a><br /><div class="row-actions"><span class='stats'><a href="?page=manage_releases&release=<?php release_id(); ?>&amp;ribcage_action=stats&amp;_wpnonce=<?php echo $nonce ?>">Stats</a></span> | <span class='edit'><a href="?page=manage_releases&release=<?php release_id(); ?>&amp;ribcage_action=edit&amp;_wpnonce=<?php echo $nonce ?>">Edit</a></span> | <span class='reviews'><a href="?page=manage_releases&release=<?php release_id(); ?>&amp;ribcage_action=reviews&amp;_wpnonce=<?php echo $nonce ?>">Reviews</a></span> | <span class='delete'><a class='submitdelete' href='?page=manage_releases&release=<?php release_id(); ?>&amp;ribcage_action=delete&amp;_wpnonce=<?php echo $nonce ?>' onclick="if ( confirm('You are about to delete \'<?php artist_name(); ?> - <?php release_title(); ?>\'\n  \'Cancel\' to stop, \'OK\' to delete.') ) { return true;}return false;">Delete</a></span></div></td>
							<td class="column-name"><?php echo date('j F Y',strtotime($release['release_date'])); ?></td>
							<td class="column-name"><?php release_downloads(); ?></td>
							<td class="column-name"><?php //remote_downloads(); ?></td>
							<td class="column-name"><?php //echo number_format(remote_downloads(FALSE)+release_downloads(FALSE)); $total_downloads = $total_downloads + remote_downloads(FALSE)+release_downloads(FALSE); update_option('ribcage_total_downloads', $total_downloads); ?></td>
							</tr>
							<?php endwhile; ?>
						</tbody>
					</table>
			</form>
			<p>Served <?php echo number_format($total_downloads) ?> downloads so far.</p>
	</div>
	<?php
        update_option('ribcage_total_downloads', $total_downloads);
}

/**
 * Administration panel for adding a release.
 *
 * @return void
 */
function ribcage_add_release() {
	global $release, $artist, $tracks, $track;
	
	?>
	<div class="wrap">
	<div id="icon-options-general" class="icon32"><br /></div>
	<?php
	if (isset($_POST['release'])) {
		$release = $_POST['release'];
		$release = stripslashes($release);
		$release = unserialize($release);
	}
	
	unset($_POST['release']);
	unset($_POST['Submit']);
	
	// Stage 4 - Add the release to the database.
	if (isset($_REQUEST['ribcage_action']) && $_REQUEST['ribcage_action'] == 'add_release' && $_REQUEST['ribcage_step'] == '4'){ 
		?>
		<h2>Add Release - Adding To Database</h2>
		<?php
		// Load everything we have so far.
		$release = get_transient('ribcage_temp_data');
		$release = unserialize($release);
		
		$tracks = get_transient('ribcage_temp_tracks');
		$tracks = unserialize($tracks);
		
		// Correct the data we have here with additions from the previous form.
		foreach ($_POST as $key => $var) {
			if (preg_match("/^track_stream_/",$key)) {
				$number = str_replace('track_stream_','',$key);
				$number = $number - 1;
				$tracks [$number]['track_stream'] = $var;
				continue;
			}
			$release[$key] = $var;
		}
		
		// Save all of this just in case we have a problem.
		set_transient('ribcage_temp_data',serialize($release),60*60);
		set_transient('ribcage_temp_tracks',serialize($release),60*60);
		
		// Checks on the data so far.
		foreach ($tracks as $track) {
			if (false === @file_get_contents($track['track_stream'])){
				$errors[] = '<p>The streaming file for the track '.$track['track_title'].' does not exist at <code>'.$track['track_stream']. '</code>.</p>';
				//$fatal_error = true;
			}
		}
		
		$formats = array('mp3','ogg','flac');
		
		foreach ($formats as $format) {
			if ($release["release_$format"]){
				if (!file_exists(ABSPATH.$release["release_$format"])){
					$errors[] = '<p>The file you set for the '.$format.' downloads does not exist at <code>'.$release["release_$format"]. '</code>.</p>';
					$missing_uploads = true;
				}
			}
		}
		
		if ($release["release_one_sheet"]) {
			if (!file_exists(ABSPATH.$release["release_one_sheet"])){
				$errors[] = '<p>The file you set for the one sheet does not exist at <code>'.$release["release_one_sheet"]. '</code>.</p>';
				$missing_uploads = 1;
			}
		}
		
		foreach ($formats as $format) {
			if ($release["release_torrent_$format"]){
				if (false === @file_get_contents($release["release_torrent_$format"],0,null,0,1)) {
					$errors[] = '<p>The file you set for the '.$format.' torrent does not exist at <code>'.$release["release_torrent_$format"]. '</code>.</p>';
				}
			}
		}
		
		$sizes = array ('tiny','large','huge');
		
		foreach ($sizes as $size) {
			if ($release["release_cover_image_$size"]){
				if (false === @file_get_contents($release["release_cover_image_$size"],0,null,0,1)) {
					$errors[] = '<p>The '.$size.' cover image does not exist at <code>'.$release["release_cover_image_$size"]. '</code>.</p>';
					$missing_uploads = true;
				}
				$check_images = true;
			}
		}
		
		if (!$check_images) {
			$errors[] = '<p>No cover images have been set!</p>';
		}
		
		if ($missing_uploads) {
			$errors[] = "<p>We've got missing files here, don't forget to upload them!</p>";
		}
		
		// Show errors.
		if (is_array($errors)) { ?>
			<h3>Errors</h3>
			<p>The following errors have been found in what you have entered.</p> 
			<?php
			foreach ($errors as $error) {
				print $error;
			}
			// TODO Set some kind of flag to remind the user that their are incomplete elements of the release.
			if ($fatal_error){ ?>
				<p>There are too many errors to continue, please go back and correct them.</p>
				<p class="submit">
					<input type="submit" name="Submit" class="button-primary" value="Back" />
				</p>
				<?php
				return;
			}
		}
		?>
		<p>Added <?php release_title(); ?> to the database.</p>
		<?php
		global $wpdb;
			
		$wpdb->show_errors();
		
		// Add release to database
		$string_keys = implode(array_keys($release),",");
		$string_values = "'".implode(array_values($release),"','")."'";
		
		$sql = "INSERT INTO ".$wpdb->prefix."ribcage_releases
					($string_keys)
					VALUES
					($string_values)";
		//echo $sql;
		$results = $wpdb->query($sql);
		
		// Add tracks to database
		foreach ($tracks as $tr) {
			$tr['track_title'] = mysql_real_escape_string($tr['track_title']);
			$string_keys = implode(array_keys($tr),",");
			$string_values = "'".implode(array_values($tr),"','")."'";
			
			$sql =" 
			INSERT INTO wp_ribcage_tracks 
			($string_keys)
			VALUES
			($string_values)
			";
			
			//echo $sql;
			$results = $wpdb->query($sql);
		}
		
		delete_transient('ribcage_temp_tracks');
		delete_transient('ribcage_temp_data');
		
		delete_transient('ribcage_allow_download');
		delete_transient('ribcage_allow_torrent');
		
		$wpdb->hide_errors();
		
		if (!$release['release_mbid']) {
			echo "<p>Don't forget to add the release to <a href=\"http://musicbrainz.org\">MusicBrainz</a>. It will make your life a lot easier!</p>";
		}

		return 0;
	}
	
	// Stage 3 - Where are the files related to this release?
	elseif (isset($_REQUEST['ribcage_action']) && $_REQUEST['ribcage_action'] == 'add_release' && $_REQUEST['ribcage_step'] == '3') {
		// Load everything up of any interest. By now we should have a fair bit.
		$release = get_transient('ribcage_temp_data');
		$release = unserialize($release);
		
		$allow_download = get_transient('ribcage_allow_download');
		$allow_torrent = get_transient('ribcage_allow_torrent');
		
		// Set the tracks from the previous stage $_POST
		$total_tracks = $release['release_tracks_no'];
		$t = 1;
		
		$artist = get_artist($release['release_artist']);
		$artist_slug = $artist['artist_slug'];
		
		while (count($tracks) != $total_tracks){
			$tracks [] = array(
				'track_number'=>$_POST["track_number_$t"],
				'track_title'=>$_POST["track_title_$t"],
				'track_time'=>$_POST["track_time_$t"],
				'track_release_id' => $release['release_id'],
				'track_mbid' => $_POST["track_mbid_$t"],
				'track_slug' => ribcage_slugize($_POST["track_title_$t"]),
				'track_stream' => get_option('siteurl').get_option('ribcage_file_location').'audio'.ribcage_slugize($_POST['track_title_$t'])."/$artist_slug/".ribcage_slugize($release['release_title']).'/stream/'.str_pad($t,2, "0", STR_PAD_LEFT).'.mp3'
				);
			$t++;
		}
		
		set_transient('ribcage_temp_tracks',serialize($tracks),60*60);
		?>
		<h2>Add Release - Upload Files</h2>
		<form action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>&ribcage_action=add_release&ribcage_step=4" method="post" id="ribcage_add_release" name="add_release">
		<?php if ($allow_download == 1) { ?>
		<h3>Downloads</h3>
		<p>At the moment Ribcage has no facility to upload downloads for your release. But it has made the following guess as to where your downloads might be - please check them.</p>
		<table class="form-table">             
		<?php
		
		if ($release['release_mp3']) {
			?>
			<tr valign="top">
				<th scope="row"><label for="release_mp3">.zip Location For MP3</label></th> 
				<td>
				<input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_mp3']; ?>" name="release_mp3" id="release_mp3" maxlength="200" />
				</td> 
			</tr>
			<?php
			
		}
		
		if ($release['release_ogg']) {
			?>
			<tr valign="top">
				<th scope="row"><label for="release_ogg">.zip Location For Ogg</label></th> 
				<td>
				<input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_ogg']; ?>" name="release_ogg" id="release_ogg" maxlength="200" />
				</td> 
			</tr>
			<?php
		}
		
		if ($release['release_flac']) {
			?>
			<tr valign="top">
				<th scope="row"><label for="release_ogg">.zip Location For Flac</label></th> 
				<td>
				<input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_flac']; ?>" name="release_flac" id="release_flac" maxlength="200" />
				</td> 
			</tr>
			<?php
			}
		}
		else {
			unset($release['release_mp3']);
			unset($release['release_ogg']);
			unset($release['release_flac']);
		}
		?>
		</table>
		<?php if ($allow_torrent == 1) { ?>
		<h3>Torrents</h3>
		<p>The locations of your torrents for those downloads.</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="release_torrent_mp3">Torrent For MP3</label></th> 
				<td>
				<input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_torrent_mp3']; ?>" name="release_torrent_mp3" id="release_torrent_mp3" maxlength="200" />
				</td> 
			</tr>
			<tr valign="top">
				<th scope="row"><label for="release_torrent_ogg">Torrent For Ogg</label></th> 
				<td>
				<input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_torrent_ogg']; ?>" name="release_torrent_ogg" id="release_torrent_ogg" maxlength="200" />
				</td> 
			</tr>
			<tr valign="top">
				<th scope="row"><label for="release_torrent_flac">Torrent For Flac</label></th> 
				<td>
				<input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_torrent_flac']; ?>" name="release_torrent_flac" id="release_torrent_flac" maxlength="200" />
				</td> 
			</tr>
		</table>
		<?php } 
		else {
			unset($release['release_torrent_mp3']);
			unset($release['release_torrent_ogg']);
			unset($release['release_torrent_flac']);
		}
		?>
		<h3>Streams</h3>
		<p>The following is our guess where the files to stream your release are located.</p>
		<table width="800px"> 
			<thead>
			<tr>
				<td>No</td>
				<td>Track Name</td>
				<td>Stream Location</td>		
			</tr>
			</thead>
			<?php $track_count = 1; ?>
			<?php while (have_tracks ()) : the_track(); ?>
			<tr>
				<th scope="row">
					<?php track_no(); ?>
				</th>
				<td>
					<?php track_title(); ?>
				</td>
				<td>
					<input type="text" style="width:500px;" class="regular-text" value="<?php echo $track['track_stream']; ?>" name="track_stream_<?php echo $track_count; ?>" id="track_stream_<?php echo $track_count; ?>" maxlength="200" />											
				</td>
			</tr>
			<?php $track_count++;?>
			<?php endwhile; ?>
		</table>
		<h3>Artwork</h3>
		<p>The following is our guess where the images for the artwork for your release are located.</p>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><label for="release_cover_image_tiny">Tiny Cover Image</label></th> 
				<td>
				<input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_cover_image_tiny']; ?>" name="release_cover_image_tiny" id="release_cover_image_tiny" maxlength="200" />
				</td> 
			</tr>
			<tr valign="top">
				<th scope="row"><label for="release_cover_image_large">Large Cover Image</label></th> 
				<td>
				<input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_cover_image_large']; ?>" name="release_cover_image_large" id="release_cover_image_large" maxlength="200" />
				</td> 
			</tr>
			<tr valign="top">
				<th scope="row"><label for="release_cover_image_huge">Huge Cover Image</label></th> 
				<td>
				<input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_cover_image_huge'] ?>" name="release_cover_image_huge" id="release_cover_image_huge" maxlength="200" />
				</td> 
			</tr>
		</table>
		<h3>Press</h4>
		<p>Release One Sheet  <input type="text" style="width:480px;" class="regular-text code" value="<?php print $release['release_one_sheet']; ?>" name="release_one_sheet" id="release_one_sheet" maxlength="200" /></p>
		<p class="submit">
			<input type="submit" name="Submit" class="button-primary" value="Next" />
		</p>
		</form>
		<?php
		set_transient('ribcage_temp_data',serialize($release),60*60);
	}
	
	// Stage 2 - Check release tracks are correct.
	elseif (isset($_REQUEST['ribcage_action']) && $_REQUEST['ribcage_action'] == 'add_release') {
		// Get the tracks that have been set, if we have been sent any.
		$tracks = get_transient('ribcage_temp_tracks');
		$tracks = unserialize($tracks);
		
		if (!$tracks) {
			$track_count = 0;
			while ($track_count < $_POST['release_tracks_no']) {
				$track_count++;
				$tracks [] = array(
						'track_title' => '',
				    	'track_time' => '',
						'track_release_id' => $release['release_id'],
						'track_mbid' => '',
						'track_slug' => '',
						'track_number' => $track_count,
						'track_stream' => ''
						);
			}
		}
		
		$artist = get_artist($_POST['release_artist']);
		$release['release_artist'] = $_POST['release_artist'];
		$release['release_tracks'] = $tracks;
		
		set_transient('ribcage_allow_download',$_POST['allow_download'], 60*60);
		set_transient('ribcage_allow_torrent',$_POST['allow_torrent'], 60*60);
		
		unset($_POST['allow_download']);
		unset($_POST['allow_torrent']);
		
		// Whack all the inputed variables into $release
		foreach ($_POST as $key => $var) {
			$release[$key] = $var;
		}
		
		// If we don't have any data from Musicbrainz then this is the time to guess some stuff.
		$test = get_transient('ribcage_got_mb');
		
		if ($test != 1) {
			$artist = get_artist($release['release_artist']);
			$artist_slug = $artist['artist_slug'];
			
			$release = array_merge($release,array(
				'release_cover_image_tiny' => get_option('siteurl').get_option('ribcage_image_location').'covers/tiny/'.$release['release_slug'].'.jpg',
				'release_cover_image_large' => get_option('siteurl').get_option('ribcage_image_location').'covers/large/'.$release['release_slug'].'.jpg',
				'release_cover_image_huge' =>get_option('siteurl').get_option('ribcage_image_location').'covers/huge/'.$release['release_slug'].'.jpg',
				'release_mp3' => get_option('ribcage_file_location').$artist_slug.'/'.$release['release_slug'].'/download/zip/'.$release['release_slug'].'-mp3.zip',
				'release_ogg' => get_option('ribcage_file_location').$artist_slug.'/'.$release['release_slug'].'/download/zip/'.$release['release_slug'].'-ogg.zip',
				'release_flac' => get_option('ribcage_file_location').$artist_slug.'/'.$release['release_slug'].'/download/zip/'.$release['release_slug'].'-flac.zip',
				'release_torrent_mp3'=>'',
				'release_torrent_ogg'=>'',
				'release_torrent_flac'=>'',
		'release_one_sheet'=>get_option('siteurl').get_option('ribcage_file_location').'pdf/onesheets/'.$release['release_slug'].'.pdf',
			)
			);
		}
		?>
		<h2>Add Release - Track Details</h2>
		<p>Please check the following details for <?php artist_name(); ?> - <?php release_title();?>.</p>
		<?php ribcage_tracks_form(); ?>
		</div>
		<?php
		
		// Dump this because we already have tracks save in $tracks, hased in the database as ribcage_temp_tracks.
		unset($release['release_tracks']);
		
		// Save data.
		$saved_temp = unserialize(get_transient('ribcage_temp_data'));

		if($saved_temp) {
			$temp = array_merge($release,$saved_temp); 
			$temp = serialize($temp);
		}
		else {
			$temp = serialize($release);
		}

		set_transient('ribcage_temp_data',$temp, 60*60);
		
		return 0;
	}
	else {
	if (isset($_POST['lookup']) && $_POST['lookup'] != '') { ?>
		<h2>Add Release - Release Details</h2> <?php
		if ($_POST['lookup'] == 'Lookup')	{
			$mbid = $_POST['musicbrainz_id'];		
			$result = mb_get_release($mbid);

			if (is_wp_error($result)){
				?>
				<?php
				switch ($result->get_error_code()){
					case 'mb_not_found': ?>
						<p>Ribcage could not find a release with a MBID of <?php echo $mbid; ?> in the Musicbrainz database.</p>
						<p>Please enter the release manually, but don't forget to add it to Musicbrainz afterwards.</p>
						<?php
					break;
					case 'artist_not_found': ?>
						// TODO Find out if we don't have this artist.
						<p><?php echo $artist; ?> is not an artist in the Ribcage database. Yet.</p>
						<p>You need to <a href="admin.php?page=add_artist">add an artist</a> before you can add their releases.</p>
						<?php
						return (1);
					break;
				}
				
				?>
				</div>
				<?php
			}
			
			$artist_slug = $artist['artist_slug'];
			
			// Let people know we got some interesting things from Musicbrainz.
			set_transient('ribcage_got_mb','1',60*60);
			
			// Guess some things about our release.
			$release = array_merge($release,array(
				'release_cover_image_tiny' => get_option('siteurl').get_option('ribcage_image_location').'covers/tiny/'.$release['release_slug'].'.jpg',
				'release_cover_image_large' => get_option('siteurl').get_option('ribcage_image_location').'covers/large/'.$release['release_slug'].'.jpg',
				'release_cover_image_huge' =>get_option('siteurl').get_option('ribcage_image_location').'covers/huge/'.$release['release_slug'].'.jpg',
				'release_mp3' => get_option('ribcage_file_location').$artist_slug.'/'.$release['release_slug'].'/download/zip/'.$release['release_slug'].'-mp3.zip',
				'release_ogg' => get_option('ribcage_file_location').$artist_slug.'/'.$release['release_slug'].'/download/zip/'.$release['release_slug'].'-ogg.zip',
				'release_flac' => get_option('ribcage_file_location').$artist_slug.'/'.$release['release_slug'].'/download/zip/'.$release['release_slug'].'-flac.zip',
				'release_torrent_mp3'=>'',
				'release_torrent_ogg'=>'',
				'release_torrent_flac'=>'',
		'release_one_sheet'=>get_option('siteurl').get_option('ribcage_file_location').'pdf/onesheets/'.$release['release_slug'].'.pdf',
			)
			);
		}
		
		$tracks = serialize($release['release_tracks']);

		// Stage 1 - Add the details of the release or correct those from Musicbrainz
		ribcage_release_form ();
		
		// Anything else goes in a transient until further notice.
		if ($release) {
			set_transient('ribcage_temp_tracks', $tracks, 60*60);

			$temp = array (
				'release_mbid'=>$release['release_mbid'],
				'release_physical_cat_no'=>$release['release_physical_cat_no'],
				'release_cover_image_tiny'=>$release['release_cover_image_tiny'],
				'release_cover_image_large'=>$release['release_cover_image_large'],
				'release_cover_image_huge'=>$release['release_cover_image_huge'],
				'release_mp3'=>$release['release_mp3'],
				'release_ogg'=>$release['release_ogg'],
				'release_flac'=>$release['release_flac'],
				'release_one_sheet'=>$release['release_one_sheet']
				);

			$temp = serialize($temp);
			set_transient('ribcage_temp_data',$temp, 60*60);
		}
	}
	// Display the start with the Musicbrainz lookup form.
	else {
		// Clear the memory decks, in case we have a half finished transaction.
		delete_transient('ribcage_temp_tracks');
		delete_transient('ribcage_temp_data');
		delete_transient('ribcage_allow_download');
		delete_transient('ribcage_allow_torrent');
	?>
		<h2>Add Release - Musicbrainz Lookup</h2>
		<form method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<p>Please enter the <a href="http://musicbrainz.org">Musicbrainz</a> ID and Ribcage will lookup the release and fill in the details automtically. This should be the Musicbrainz ID of the specific release, not the release group.</p> 
		<p>If your release does not have a Musicbrainz ID, or if you wish to enter the release entirely manually, click on Skip.</p>
		<p>Adding a track to Musicbrainz and then adding it to Ribcage makes it so you only have to type out the details of your release once. It also means that details of your release will be available on a central database, accessible to all.</p>
		<table class="form-table">
		<tr valign="top">
		<th scope="row"><label for="musicbrainz_id">Musicbrainz ID</label></th>
		<td><input type="text" name="musicbrainz_id" value="bce40d0a-6b5f-4d75-97c7-916d67d584f6" class="regular-text code"/></td>
		</tr>
		</table>
		<p class="submit">
		<input type="submit" name="lookup" class="button-primary" value="<?php _e('Lookup') ?>" /><input type="submit" name="lookup" class="button-secondary" value="<?php _e('Skip') ?>" />
		</p>
		</form>
	<?php
	}
}
	?>
	</div>
	<?php
	
}

/**
 * Displays a form with all the variables of a particular release.
 * Note, doesn't display tracks, that is done by ribcage_tracks_form.
 * 
 * @return void
 */
function ribcage_release_form () {
	global $artists, $tracks, $artist, $track, $release;
	
	?>
	<form action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>&ribcage_action=add_release" method="post" id="ribcage_add_release" name="add_release">
	<table class="form-table">             
		<tr valign="top">
			<th scope="row"><label for="release_artist">Release Artist</label></th> 
			<td>
			<?php ribcage_artists_dropdown('release_artist', $release['release_artist']); ?>
			</td> 
		</tr>
		<tr valign="top">
			<th scope="row"><label for="release_title">Release Name</label></th> 
			<td>
				<input type="text" style="width:320px;" class="regular-text code" value="<?php release_title(); ?>" name="release_title" id="release_title" maxlength="200" />										
			</td> 
		</tr>
		<tr valign="top">
			<th scope="row"><label for="release_title">Release Slug</label></th> 
			<td>
				<input type="text" style="width:320px;" class="regular-text code" value="<?php release_slug(); ?>" name="release_slug" id="release_slug" maxlength="200" /><span class="description">The URL you want for the release after the artist name, for example <a href="<?php echo home_url(); ?>/artist_name/release_slug</span>										
			</td> 
		</tr>
		<tr valign="top">
			<th scope="row"><label for="release_title">Release Date</label></th> 
			<td>
				<input type="text" style="width:320px;" class="regular-text code" value="<?php echo $release['release_date']; ?>" name="release_date" id="release_date" maxlength="200" /><span class="description">When is the record going to come out?</span>										
			</td> 
		</tr>
		<tr valign="top">
			<th scope="row"><label for="release_id">Catalogue Number</label></th> 
			<td>
				<?php echo get_option('ribcage_mark'); ?><input type="text" style="width:30px;" class="regular-text code" value="<?php echo $release['release_id']; ?>" name="release_id" id="release_id" maxlength="10" /><span class="description">This will be padded to be three digits</span>									
			</td> 
		</tr>
		<tr valign="top">
			<th scope="row"><label for="release_tracks_no">Number Of Tracks</label></th> 
			<td>
				<input type="text" style="width:30px;" class="regular-text code" value="<?php echo $release['release_tracks_no']; ?>" name="release_tracks_no" id="release_tracks_no" />									
			</td> 
		</tr>
		<tr valign="top">
			<th scope="row"><label for="release_time">Length of Release</label></th> 
			<td>
				<input type="text" style="width:70px;" class="regular-text code" value="<?php echo $release['release_time']; ?>" name="release_time" id="time" /><span class="description">Length of release in hh:mm:ss</span>	
			</td> 
		</tr>
		<tr valign="top">
			<th scope="row"><label for="release_physical">Physical Release</label></th>
			<td>
				<select name="release_physical" id="release_physical">
					<?php if (isset($release['release_physical'])) { ?>
					<option selected value ="<?php echo $release['release_physical'];?>"><?php if ($release['release_physical'] == 1) { echo 'Yes';} else { echo 'No';};?></option>
					<option value="<?php if ($release['release_physical'] == 1) { echo '0'; } else { echo '1'; }?>"><?php if ($release['release_physical'] == 1){ echo 'No'; } else { echo 'Yes'; };?></option>
					<?php } ?>
					<option value ="0">No</option>
					<option value = "1">Yes</option>
				</select>
				<span class="description">Is there a physical version of this release you are intending to sell?</span>									
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="allow_downloads">Allow Downloads?</label></th>
			<td>
				<select name="allow_download" id="allow_downloads">
					<option selected value="1">Yes</option>
					<option value="0">No</option>
				</select>							
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="allow_torrents">Allow Downloads By Torrent?</label></th>
			<td>
				<select name="allow_torrent" id="allow_torrents">
					<option selected value="1">Yes</option>
					<option value="0">No</option>
				</select>								
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="release_blurb_short">Short Description Of Release</label></th>
			<td><textarea name="release_blurb_short" id="release_blurb_short" rows="5" cols="80"></textarea>
			</td>
		</tr>
		<tr valign="top">
			<th scope="row"><label for="release_blurb_long">Long Description Of Release</label></th>
			<td><textarea name="release_blurb_long" id="release_blurb_long" rows="15" cols="80"></textarea>						
			</td>
		</tr>
	</table>
	<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="Next" />
	</p>
	</form>
	<?php
}

/**
 * Displays form for with tracks, allowing adding or editing.
 *
 * @return void
 */
function ribcage_tracks_form () {
	global $release, $tracks, $track;
	?>
	<form action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>&ribcage_step=3" method="post" id="ribcage_add_release" name="add_release">
	<table width="200px"> 
		<thead>
		<tr>
			<td>No</td>
			<td>Track Name</td>
			<td>Length</td>		
		</tr>
		</thead>
		<?php $track_count = 1; ?>
		<?php while (have_tracks ()) : the_track(); ?>
		<tr>
			<th scope="row">
				<input type="text" style="width:30px;" class="regular-text" value="<?php track_no(); ?>" name="track_number_<?php echo $track_count; ?>" id="track_number_<?php echo $track_count; ?>" maxlength="200" />
			</th>
			<td>
				<input type="text" style="width:320px;" class="regular-text" value="<?php track_title(); ?>" name="track_title_<?php echo $track_count; ?>" id="track_title_<?php echo $track_count; ?>" maxlength="200" />						
			</td>
			<td>
				<input type="text" style="width:70px;" class="regular-text" value="<?php echo $track['track_time']; ?>" name="track_time_<?php echo $track_count; ?>" id="track_time_<?php echo $track_count; ?>" maxlength="200" />
				<input type="hidden" name="track_mbid_<?php echo $track_count; ?>" value='<?php echo $track['track_mbid'] ?>' />											
			</td>
		</tr>
		<?php $track_count++;?>
		<?php endwhile; ?>
	</table>
	<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="Save Changes" />
	</p>
	</form>
	<?php
}

/**
 * Add a review of a specific release.
 *
 * @return void
 */
function ribcage_manage_reviews() {
	global $releases, $release,$artist, $tracks, $track;

        $release = get_release($_REQUEST['release'],false,true);
        $reviews = $release['release_reviews'];
        $artist['artist_name'] = get_artistname_by_id($release['release_artist']);
        
        ?>
        <div class="wrap">
		<h2>Manage Reviews of <?php artist_name(); ?> - <?php release_title(); ?></h2>
        <?php
        if (count($reviews) == 0) {
            echo "<p>No reviews yet. Why not add one now?</p>";
        }
        else {
            register_column_headers('ribcage-manage-reviews',
            array (
		'cb'=>'<input type="checkbox" />',
		'review_'=>'Reviewer'
            )
            );
            
            echo "<pre>".print_r($reviews)."</pre>";
        }
        ?>
                <h3>Add a review</h3>
                <table class="form-table">
                <tr valign="top">
                    <th scope="row"><label for="review_url">Review URL</label></th>
                    <td><input type="text" name="review_url" value="" class="regular-text code"/><span class="description">The URL of the review, if the review is online.</span>								</td>
		</tr>
		<tr valign="top">
                    <th scope="row"><label for="review_url">Publication</label></th>
                    <td><input type="text" name="review_url" value="" class="regular-text code"/><span class="description">The name of the publication that reviewed the release</span>
					</td>
				</tr>
		</table>
		<p class="submit">
                    <input type="submit" name="Submit" class="button-primary" value="Add Review" />
                </p>
		</form>
        </div>
        <?php
}

/**
 * Produces a page of statistics about the release we have.
 *
 * @return void
 */
function ribcage_release_stats () {
    echo "Stats here";
}
?>
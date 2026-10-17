<?php
/** Standalone WP shims with fault injection; never contacts or writes a real site. */
define( 'ABSPATH', __DIR__ . '/' );
define( 'SA_PC_VERSION', 'test' );
define( 'SA_PC_URL', '/plugin/' );
define( 'SA_PC_DIR', sys_get_temp_dir() . '/sa-pc-test-' . getmypid() . '/' );
@mkdir( SA_PC_DIR . 'data', 0777, true );
@mkdir( SA_PC_DIR . 'assets/images', 0777, true );
@mkdir( SA_PC_DIR . 'uploads', 0777, true );
copy( dirname( __DIR__ ) . '/data/provinces.json', SA_PC_DIR . 'data/provinces.json' );
$pixel = base64_decode( 'UklGRjYAAABXRUJQVlA4ICoAAACQAQCdASoCAAIAAUAmJaACdLoAA5gA/vgjb/8bx1t9be3n/wG6+LZfAAA=' );
file_put_contents( SA_PC_DIR . 'assets/images/alborz.webp', $pixel );
file_put_contents( SA_PC_DIR . 'old.webp', $pixel );
file_put_contents( SA_PC_DIR . 'assets/images/manifest.json', json_encode( array( 'alborz' => array( 'file' => 'alborz.webp', 'sha256' => hash( 'sha256', $pixel ), 'bytes' => strlen( $pixel ), 'width' => 2, 'height' => 2 ) ) ) );
class WP_Error {
 private $code; private $message;
 public function __construct( $code, $message ) { $this->code = $code; $this->message = $message; }
 public function get_error_message() { return $this->message; }
 public function get_error_code() { return $this->code; }
}
class SA_PC_Test_Json extends RuntimeException { public $success; public $payload; public function __construct( $success, $data ) { $this->success = $success; $this->payload = $data; } }
function sa_pc_assert( $ok, $message ) { if ( ! $ok ) { throw new RuntimeException( $message ); } }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function add_action( ...$args ) { $GLOBALS['hooks'][] = $args; }
function add_filter( ...$args ) { $GLOBALS['hooks'][] = $args; }
function current_user_can( $cap, ...$args ) { return $GLOBALS['can'] && ( 'edit_post' !== $cap || $GLOBALS['can_edit'] ); }
function get_post( $id ) { return $GLOBALS['posts'][ $id ] ?? null; }
function get_post_type( $id ) { return isset( $GLOBALS['posts'][ $id ] ) ? $GLOBALS['posts'][ $id ]->post_type : 'attachment'; }
function get_post_status( $id ) { return $GLOBALS['posts'][ $id ]->post_status ?? ''; }
function post_password_required( $id ) { return $GLOBALS['password']; }
function get_option( $key, $default = false ) { return $GLOBALS['options'][ $key ] ?? $default; }
function add_option( $key, $value, ...$args ) {
 if ( array_key_exists( $key, $GLOBALS['options'] ) || ( $GLOBALS['fault'] === 'backup' && strpos( $key, 'journal' ) !== false ) ) { return false; }
 $GLOBALS['options'][ $key ] = $value; return true;
}
function update_option( $key, $value, ...$args ) { if ( $GLOBALS['fault'] === 'commit' && strpos( $key, 'journal' ) !== false ) { return false; } $GLOBALS['options'][ $key ] = $value; return true; }
function delete_option( $key ) { unset( $GLOBALS['options'][ $key ] ); return true; }
function maybe_serialize( $value ) { return serialize( $value ); }
function wp_cache_delete( ...$args ) {}
function wp_generate_uuid4() { return 'test-lock-' . ++$GLOBALS['uuid']; }
class SA_PC_Test_DB {
 public $options = 'IR_options';
 public function prepare( $sql, ...$args ) {
  sa_pc_assert( $sql === 'DELETE FROM IR_options WHERE option_name = %s AND option_value = %s', 'Only compare-and-delete a specific owned lock, never broad SQL deletion' ); return $args;
 }
 public function query( $args ) {
  list( $key, $value ) = $args;
  if ( $GLOBALS['fault'] === 'swap_lock' ) { $GLOBALS['options'][ $key ] = array( 'token' => 'new-worker', 'time' => time() ); }
  if ( isset( $GLOBALS['options'][ $key ] ) && serialize( $GLOBALS['options'][ $key ] ) === $value ) { unset( $GLOBALS['options'][ $key ] ); return 1; }
  return 0;
 }
}
function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][ $id ][ $key ] ?? ( $single ? '' : array() ); }
function update_post_meta( $id, $key, $value ) {
 if ( ( $GLOBALS['fault'] === 'alt' && $key === '_wp_attachment_image_alt' ) || ( $GLOBALS['fault'] === 'rollback' && $key === '_thumbnail_id' ) ) { return false; }
 $GLOBALS['meta'][ $id ][ $key ] = $value; return true;
}
function get_post_thumbnail_id( $id ) { return (int) get_post_meta( $id, '_thumbnail_id', true ); }
function delete_post_thumbnail( $id ) {
 $GLOBALS['events'][] = 'remove:' . $id . ':' . get_post_thumbnail_id( $id );
 if ( $GLOBALS['fault'] === 'remove' ) { return false; }
 unset( $GLOBALS['meta'][ $id ]['_thumbnail_id'] ); return true;
}
function set_post_thumbnail( $id, $attachment ) {
 $GLOBALS['events'][] = 'set:' . $id . ':' . $attachment;
 if ( in_array( $GLOBALS['fault'], array( 'install', 'rollback' ), true ) ) { return false; }
 $GLOBALS['meta'][ $id ]['_thumbnail_id'] = $attachment; return true;
}
function wp_upload_bits( $name, $unused, $data ) {
 if ( $GLOBALS['fault'] === 'upload' ) { return array( 'error' => 'disk full' ); }
 $file = SA_PC_DIR . 'uploads/' . ++$GLOBALS['upload_count'] . '.webp'; file_put_contents( $file, $data ); return array( 'file' => $file, 'error' => false );
}
function wp_insert_attachment( $fields, $file, $parent, $error ) {
 if ( $GLOBALS['fault'] === 'insert' ) { return new WP_Error( 'insert', 'insert failed' ); }
 $id = ++$GLOBALS['next_id']; $GLOBALS['files'][ $id ] = $file; $GLOBALS['attachments'][ $id ] = $fields; return $id;
}
function wp_generate_attachment_metadata( $id, $file ) { if ( $GLOBALS['fault'] === 'concurrent' ) { $GLOBALS['meta'][240]['_thumbnail_id'] = 999; } return $GLOBALS['fault'] === 'metadata' ? array() : array( 'width' => 2, 'height' => 2, 'file' => basename( $file ) ); }
function wp_update_attachment_metadata( $id, $value ) { if ( $GLOBALS['fault'] === 'metadata_enrich' ) { $value['optimizer'] = 'extra-compatible-data'; } if ( $GLOBALS['fault'] !== 'metadata_save' ) { $GLOBALS['meta'][ $id ]['_wp_attachment_metadata'] = $value; } }
function wp_get_attachment_metadata( $id ) { return get_post_meta( $id, '_wp_attachment_metadata', true ); }
function get_attached_file( $id ) { return $GLOBALS['files'][ $id ] ?? ''; }
function wp_get_attachment_url( $id ) { return 'https://example.test/uploads/' . $id . '.webp'; }
function wp_get_attachment_image( $id, $size, $icon, $attributes ) { if ( ! $id ) { return ''; } return '<img src="' . esc_url( wp_get_attachment_url( $id ) ) . '" alt="' . esc_attr( $attributes['alt'] ?? '' ) . '">'; }
function absint( $n ) { return abs( (int) $n ); }
function get_theme_mod( $key, $default = '' ) { return $default; }
function apply_filters( $hook, $value, ...$args ) { return $value; }
function has_post_thumbnail( $id ) { return (bool) get_post_thumbnail_id( $id ); }
function esc_url( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $s ) { return htmlspecialchars( $s, ENT_QUOTES, 'UTF-8' ); }
function is_admin() { return $GLOBALS['admin']; }
function is_feed() { return $GLOBALS['feed']; }
function wp_doing_ajax() { return $GLOBALS['ajax']; }
function is_singular( $type ) { return $type === $GLOBALS['singular']; }
function in_the_loop() { return $GLOBALS['loop']; }
function is_main_query() { return $GLOBALS['main']; }
function get_the_ID() { return $GLOBALS['the_id']; }
function get_queried_object_id() { return 240; }
function doing_action( $hook ) { return $GLOBALS['head']; }
function check_ajax_referer( ...$args ) { return $GLOBALS['nonce']; }
function wp_unslash( $s ) { return $s; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_-]/', '', strtolower( $s ) ); }
function wp_send_json_error( $data, $status ) { throw new SA_PC_Test_Json( false, $data ); }
function wp_send_json_success( $data ) { throw new SA_PC_Test_Json( true, $data ); }
function get_posts( $args ) { sa_pc_assert( $args['post_type'] === 'province', 'Admin only lists province destinations' ); return array_filter( $GLOBALS['posts'], static function($p){return $p->post_type === 'province';} ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function admin_url( $s ) { return '/wp-admin/' . $s; }
function wp_nonce_field( $s ) { echo '<input type="hidden" name="_wpnonce" value="test">'; }
function submit_button( ...$args ) { echo '<button>save</button>'; }
function checked( $v ) { if ( $v ) { echo 'checked'; } }
function selected( $a, $b ) { if ( (string) $a === (string) $b ) { echo 'selected'; } }
function disabled( $v ) { if ( $v ) { echo 'disabled'; } }
function wp_die( ...$args ) { throw new RuntimeException('denied'); }
function check_admin_referer( $action ) { if ( ! $GLOBALS['nonce'] ) { throw new RuntimeException('invalid nonce'); } }
function sa_pc_reset( $fault = '' ) {
 $GLOBALS['options'] = array(); $GLOBALS['events'] = array(); $GLOBALS['attachments'] = array();
 $GLOBALS['posts'] = array( 240 => (object) array( 'ID' => 240, 'post_type' => 'province', 'post_name' => 'alborz', 'post_title' => 'استان البرز', 'post_status' => 'publish' ), 241 => (object) array( 'ID' => 241, 'post_type' => 'city', 'post_name' => 'alborz', 'post_title' => 'البرز', 'post_status' => 'publish' ), 242 => (object) array( 'ID' => 242, 'post_type' => 'province', 'post_name' => 'isfahan', 'post_title' => 'اصفهان', 'post_status' => 'publish' ) );
 $GLOBALS['meta'] = array( 240 => array( '_thumbnail_id' => 77 ), 77 => array( '_wp_attachment_image_alt' => 'قدیمی' ) );
 $GLOBALS['files'] = array( 77 => SA_PC_DIR . 'old.webp' );
 $GLOBALS['can'] = $GLOBALS['can_edit'] = $GLOBALS['nonce'] = $GLOBALS['loop'] = $GLOBALS['main'] = true;
 $GLOBALS['admin'] = $GLOBALS['feed'] = $GLOBALS['ajax'] = $GLOBALS['password'] = $GLOBALS['head'] = false;
 $GLOBALS['the_id'] = 240; $GLOBALS['singular'] = 'province'; $GLOBALS['fault'] = $fault;
 $GLOBALS['next_id'] = 100; $GLOBALS['upload_count'] = 0; $GLOBALS['uuid'] = 0;
}
$GLOBALS['wpdb'] = new SA_PC_Test_DB();
require dirname( __DIR__ ) . '/includes/catalog.php';
require dirname( __DIR__ ) . '/includes/class-sa-pc-store.php';
require dirname( __DIR__ ) . '/includes/class-sa-pc-public.php';
require dirname( __DIR__ ) . '/includes/class-sa-pc-admin.php';
sa_pc_reset();
sa_pc_assert( 31 === count( sa_pc_catalog() ), 'Exact 31-province manifest' );
sa_pc_assert( 'ک ی' === sa_pc_normalize_name( "استان ك\xE2\x80\x8Cي" ), 'Persian normalization without fuzzy guesses' );
sa_pc_assert( 240 === sa_pc_find_target( sa_pc_catalog()['alborz'], $GLOBALS['posts'] ), 'Only province post matches, never its namesake city' );
$ambiguous = array( (object) array( 'ID' => 1, 'post_type' => 'province', 'post_name' => 'one', 'post_title' => 'استان البرز' ), (object) array( 'ID' => 2, 'post_type' => 'province', 'post_name' => 'two', 'post_title' => 'البرز' ) );
sa_pc_assert( 0 === sa_pc_find_target( sa_pc_catalog()['alborz'], $ambiguous ), 'Ambiguous titles require manual selection' );
foreach ( array( 'upload', 'insert', 'alt', 'metadata', 'metadata_save', 'backup', 'remove', 'install', 'commit' ) as $fault ) {
 sa_pc_reset( $fault ); $result = SA_PC_Store::apply( 'alborz', 240 );
 sa_pc_assert( is_wp_error( $result ) && 77 === get_post_thumbnail_id( 240 ), 'Fault keeps/restores old assignment: ' . $fault );
 sa_pc_assert( ! get_option( 'sa_pc_lock_240' ) && ! get_option( 'sa_pc_journal_240' ), 'Fault leaves no active lock or lost recovery journal: ' . $fault );
 sa_pc_assert( is_file( get_attached_file( 77 ) ) && 'قدیمی' === get_post_meta( 77, '_wp_attachment_image_alt', true ), 'Old shared file and alt untouched: ' . $fault );
}
sa_pc_reset( 'concurrent' ); SA_PC_Store::apply( 'alborz', 240 );
sa_pc_assert( 999 === get_post_thumbnail_id(240) && ! get_option('sa_pc_journal_240'), 'Concurrent external edit is not overwritten before replacement' );
sa_pc_reset( 'rollback' ); $result = SA_PC_Store::apply('alborz',240);
sa_pc_assert( is_wp_error($result) && 'recovery' === $result->get_error_code() && 'prepared' === get_option('sa_pc_journal_240')['state'], 'Failed rollback retains durable recovery journal' );
$GLOBALS['fault'] = ''; sa_pc_assert( ! is_wp_error(SA_PC_Store::restore(240)) && 77 === get_post_thumbnail_id(240), 'Interrupted/failed operation can restore from prepared journal' );
sa_pc_reset();
$GLOBALS['can'] = false; sa_pc_assert( is_wp_error(SA_PC_Store::apply('alborz',240)) && !$GLOBALS['upload_count'], 'No capability, no media writes' );
$GLOBALS['can'] = true; $GLOBALS['can_edit'] = false; sa_pc_assert( is_wp_error(SA_PC_Store::apply('alborz',240)), 'Per-post capability enforced' );
sa_pc_reset(); sa_pc_assert( is_wp_error(SA_PC_Store::apply('alborz',241)) && is_wp_error(SA_PC_Store::apply('alborz',242)) && !$GLOBALS['upload_count'], 'City and wrong canonical province rejected before upload' );
file_put_contents( SA_PC_DIR . 'assets/images/alborz.webp', 'tampered' );
sa_pc_assert( is_wp_error(SA_PC_Store::apply('alborz',240)) && !$GLOBALS['upload_count'], 'Checksum failure prevents any mutation' );
file_put_contents( SA_PC_DIR . 'assets/images/alborz.webp', $pixel );
sa_pc_reset('metadata_enrich');sa_pc_assert(!is_wp_error(SA_PC_Store::apply('alborz',240)), 'Optimizer may enrich metadata without falsely failing a valid import');
sa_pc_reset(); $result = SA_PC_Store::apply('alborz',240);
sa_pc_assert( ! is_wp_error($result) && 101 === get_post_thumbnail_id(240), 'Successful replacement' );
sa_pc_assert( array('remove:240:77','set:240:101') === $GLOBALS['events'], 'Old assignment removed before new assignment installed' );
sa_pc_assert( sa_pc_catalog()['alborz']['alt'] === get_post_meta(101,'_wp_attachment_image_alt',true), 'Persian graphic-poster alt written to the new attachment' );
SA_PC_Store::apply('alborz',240); sa_pc_assert( 1 === $GLOBALS['upload_count'], 'Retry is idempotent, no duplicate attachment' );
require dirname( __DIR__, 3 ) . '/themes/sarzaminaryan-child/inc/featured-images.php';
sa_pc_assert( false === sa_featured_images_visible() && false === sa_featured_image_available(240,'hero') && '' === sa_featured_image_guard('<img>',240), 'Actual theme global ban stays off and province hero remains hidden' );
$before = serialize(array($GLOBALS['options'],$GLOBALS['meta']));
$body = '<p>متن مقاله</p>';
$render = SA_PC_Public::content($body);
sa_pc_assert( strpos($render,'<figure') === 0 && strpos($render,$body) !== false && 1 === substr_count(SA_PC_Public::content($render),'data-sa-province-cover='), 'Exactly one article figure; content remains unchanged after it' );
sa_pc_assert( '' === SA_PC_Public::guard('<img>',240) && '<img>' === SA_PC_Public::guard('<img>',241), 'Province thumbnail cards suppressed, other post types unchanged' );
foreach(array('admin','feed','ajax','password') as $flag){$GLOBALS[$flag]=true;sa_pc_assert($body === SA_PC_Public::content($body),'No image in '.$flag);$GLOBALS[$flag]=false;}
foreach(array('loop','main') as $flag){$GLOBALS[$flag]=false;sa_pc_assert($body===SA_PC_Public::content($body),'No image outside '.$flag);$GLOBALS[$flag]=true;}
$GLOBALS['singular']='city';sa_pc_assert($body===SA_PC_Public::content($body),'County unaffected');$GLOBALS['singular']='province';
$GLOBALS['the_id']=241;sa_pc_assert($body===SA_PC_Public::content($body),'Secondary loop unaffected');$GLOBALS['the_id']=240;
$GLOBALS['options']['sa_pc_show_in_article']=false;sa_pc_assert($body===SA_PC_Public::content($body),'Independent article switch');unset($GLOBALS['options']['sa_pc_show_in_article']);
$crop=array('old-crop',1600,700,true);sa_pc_assert($crop===SA_PC_Public::head_image_size($crop,101,'sa-hero'),'Outside head size unchanged');
$GLOBALS['head']=true;$full=SA_PC_Public::head_image_size($crop,101,'sa-hero');sa_pc_assert($full[0]===wp_get_attachment_url(101)&&$full[1]===2&&$full[2]===2,'Social head gets uncropped dimensions');$GLOBALS['head']=false;
$rank=SA_PC_Public::social_array(array('url'=>'old','alt'=>'old'));sa_pc_assert($rank['id']===101&&$rank['alt']===sa_pc_catalog()['alborz']['alt'],'Rank Math gets matching URL, ID, dimensions and alt');
sa_pc_assert(SA_PC_Public::facebook_tags(array())['og:image']===wp_get_attachment_url(101)&&SA_PC_Public::twitter_tags(array())['twitter:card']==='summary_large_image','AIOSEO image tags consistent');
ob_start();SA_PC_Public::head();$head=ob_get_clean();sa_pc_assert(strpos($head,'property="og:image"')!==false&&strpos($head,'name="twitter:image"')!==false,'Standalone social metadata');
if(!function_exists('sa_seo_head')){function sa_seo_head(){}}
ob_start();SA_PC_Public::head();$head=ob_get_clean();sa_pc_assert(strpos($head,'property="og:image"')===false&&strpos($head,'name="twitter:image"')!==false,'Theme OG is not duplicated; missing Twitter image supplied');
sa_pc_assert($before===serialize(array($GLOBALS['options'],$GLOBALS['meta'])),'Public rendering/social callbacks perform zero writes');
$GLOBALS['meta'][240]['_thumbnail_id']=999;sa_pc_assert(is_wp_error(SA_PC_Store::restore(240))&&get_post_thumbnail_id(240)===999,'Restore preserves a later manual selection');
$GLOBALS['meta'][240]['_thumbnail_id']=101;SA_PC_Store::restore(240);sa_pc_assert(get_post_thumbnail_id(240)===77&&!get_option('sa_pc_journal_240')&&is_file(get_attached_file(101)),'Restore returns old association and keeps both media files');
sa_pc_reset();$GLOBALS['options']['sa_pc_lock_240']=array('token'=>'other','time'=>time());sa_pc_assert(is_wp_error(SA_PC_Store::apply('alborz',240))&&!$GLOBALS['upload_count'],'Concurrent plugin job blocked');
sa_pc_assert(is_wp_error(SA_PC_Store::unlock(240)),'Active lock cannot be forcibly cleared');
$GLOBALS['options']['sa_pc_lock_240']['time']=time()-901;$GLOBALS['fault']='swap_lock';sa_pc_assert(is_wp_error(SA_PC_Store::unlock(240))&&get_option('sa_pc_lock_240')['token']==='new-worker','Expired-lock CAS cannot delete a newer worker lock');
$GLOBALS['fault']='';$GLOBALS['options']['sa_pc_lock_240']['time']=time()-901;sa_pc_assert(true===SA_PC_Store::unlock(240),'Explicit stale-lock recovery works');
sa_pc_reset();$_SERVER['REQUEST_METHOD']='POST';$_POST=array('slug'=>'alborz','post_id'=>'240','op'=>'apply');
foreach(array('capability','nonce','array_input','wrong_type') as $case){
 $GLOBALS['can']=$case!=='capability';$GLOBALS['nonce']=$case!=='nonce';$_POST['post_id']=$case==='array_input'?array('240'):($case==='wrong_type'?'241':'240');
 try{SA_PC_Admin::run();throw new RuntimeException('Expected denial');}catch(SA_PC_Test_Json $json){sa_pc_assert(!$json->success,'AJAX rejects '.$case);}
 sa_pc_assert(!$GLOBALS['upload_count'],'Denied AJAX never stages media');
}
$GLOBALS['can']=$GLOBALS['nonce']=true;$_POST['post_id']='240';
try{SA_PC_Admin::run();}catch(SA_PC_Test_Json $json){sa_pc_assert($json->success,'Authorized nonce-protected AJAX works');}
sa_pc_reset();
$state=serialize(array($GLOBALS['options'],$GLOBALS['meta']));ob_start();SA_PC_Admin::page();$page=ob_get_clean();
sa_pc_assert(31===substr_count($page,'data-sa-pc-row data-slug=')&&strpos($page,'data-sa-pc-batch')!==false&&strpos($page,'_wpnonce')!==false,'Admin preview lists exactly 31 rows and nonce-protected settings, no auto-import');
sa_pc_assert($state===serialize(array($GLOBALS['options'],$GLOBALS['meta'])),'Opening admin preview does not mutate media or options');
$GLOBALS['nonce']=false;try{SA_PC_Admin::settings();throw new RuntimeException('nonce was not checked');}catch(RuntimeException $e){sa_pc_assert('invalid nonce'===$e->getMessage(),'Settings nonce checked before write');}
sa_pc_assert($state===serialize(array($GLOBALS['options'],$GLOBALS['meta'])),'Invalid settings nonce causes no writes');
$GLOBALS['nonce']=true;SA_PC_Store::apply('alborz',240);
define('RANK_MATH_VERSION','test');ob_start();SA_PC_Public::head();sa_pc_assert(''===ob_get_clean(),'Dedicated SEO provider receives no duplicate plugin tags');
define('REST_REQUEST',true);sa_pc_assert($body===SA_PC_Public::content($body),'REST is unchanged');
$store=file_get_contents(dirname(__DIR__).'/includes/class-sa-pc-store.php');sa_pc_assert(strpos($store,'wp_delete_attachment')===false&&strpos($store,'wp_update_post')===false,'No attachment deletion or article-content rewriting code');
echo "PASS: 31-province mapping, safe replacement/rollback, fault injection, owned locks, nonce/capabilities, article-only display and social metadata\n";

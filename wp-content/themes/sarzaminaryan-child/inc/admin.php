<?php
/**
 * Admin: unified "سرزمین آریان" menu, dashboard, list columns, filters, styles.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Top-level menu that hosts all entity CPTs (show_in_menu = 'sarzaminaryan').
 */
function sa_admin_menu() {
	add_menu_page( 'سرزمین آریان', 'سرزمین آریان', 'edit_posts', 'sarzaminaryan', 'sa_admin_dashboard_page', 'dashicons-location-alt', 4 );
	add_submenu_page( 'sarzaminaryan', 'داشبورد محتوا', 'داشبورد محتوا', 'edit_posts', 'sarzaminaryan', 'sa_admin_dashboard_page' );

	// Taxonomy screens under the same menu (CPT submenus are added automatically by WordPress).
	foreach ( sa_taxonomies_config() as $tax => $t ) {
		if ( ! taxonomy_exists( $tax ) ) {
			continue;
		}
		$first = null;
		foreach ( $t['applies_to'] as $type ) {
			if ( post_type_exists( $type ) ) {
				$first = $type;
				break;
			}
		}
		if ( ! $first ) {
			continue;
		}
		$cap = 'province_tax' === $tax ? 'manage_options' : 'manage_categories';
		add_submenu_page( 'sarzaminaryan', $t['plural'], '— ' . $t['plural'], $cap, 'edit-tags.php?taxonomy=' . $tax . '&post_type=' . $first );
	}
}
add_action( 'admin_menu', 'sa_admin_menu' );

/**
 * Keep the parent menu highlighted on taxonomy screens.
 *
 * @param string $parent_file Parent file.
 * @return string
 */
function sa_admin_parent_file( $parent_file ) {
	$screen = get_current_screen();
	if ( $screen && isset( sa_taxonomies_config()[ $screen->taxonomy ] ) ) {
		return 'sarzaminaryan';
	}
	return $parent_file;
}
add_filter( 'parent_file', 'sa_admin_parent_file' );

/**
 * Dashboard page: counts, incomplete entities, quick links.
 */
function sa_admin_dashboard_page() {
	echo '<div class="wrap sa-dash"><h1>سرزمین آریان — داشبورد محتوا</h1>';
	echo '<p>مدل داده نسخه ' . esc_html( sa_fa_digits( SA_MODEL_VERSION ) ) . ' · قالب فرزند نسخه ' . esc_html( sa_fa_digits( SA_CHILD_VERSION ) ) . ' · حالت دروازه‌ی انتشار: <strong>' . ( 'hard' === sa_gate_mode() ? 'سخت‌گیر' : 'هشدار' ) . '</strong></p>';
	echo '<div class="sa-dash__cards">';
	foreach ( sa_entity_types() as $type ) {
		$c   = wp_count_posts( $type );
		$obj = get_post_type_object( $type );
		printf(
			'<a class="sa-dash__card" href="%s"><span class="dashicons %s"></span><strong>%s</strong><span>%s منتشرشده · %s پیش‌نویس</span><em>+ افزودن</em></a>',
			esc_url( admin_url( 'post-new.php?post_type=' . $type ) ),
			esc_attr( $obj->menu_icon ),
			esc_html( $obj->labels->name ),
			esc_html( sa_fa_digits( (int) $c->publish ) ),
			esc_html( sa_fa_digits( (int) $c->draft ) )
		);
	}
	echo '</div>';

	// Incomplete drafts.
	$drafts = get_posts(
		array(
			'post_type'      => sa_entity_types(),
			'post_status'    => array( 'draft', 'pending' ),
			'posts_per_page' => 20,
			'orderby'        => 'modified',
			'order'          => 'DESC',
		)
	);
	echo '<h2>پیش‌نویس‌های اخیر و موارد ناقص</h2>';
	if ( ! $drafts ) {
		echo '<p>پیش‌نویسی وجود ندارد. 🎉</p>';
	} else {
		echo '<table class="widefat striped"><thead><tr><th>عنوان</th><th>نوع</th><th>وضعیت سطح ۷</th><th>آخرین تغییر</th></tr></thead><tbody>';
		foreach ( $drafts as $d ) {
			printf(
				'<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_url( get_edit_post_link( $d ) ),
				esc_html( get_the_title( $d ) ? get_the_title( $d ) : '(بدون عنوان)' ),
				esc_html( sa_entity_label( $d->post_type ) ),
				sa_gate_badge( $d->ID ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				esc_html( sa_fa_digits( get_the_modified_date( 'Y/m/d', $d ) ) )
			);
		}
		echo '</tbody></table>';
	}

	echo '<h2>ترتیب پیشنهادی تولید محتوا (سطح ۶ — لینک‌های داخلی همیشه مقصد داشته باشند)</h2>';
	echo '<ol><li>۳۱ استان (صفحه‌های هاب)</li><li>شهرهای هر استان</li><li>جاذبه‌های هر شهر</li><li>غذاها و سوغات هر شهر</li><li>مسیرهای سفر</li><li>نوشته‌های وبلاگ</li></ol>';
	echo '<p><a class="button button-primary" href="' . esc_url( admin_url( 'customize.php?autofocus[panel]=sa_panel' ) ) . '">تنظیمات قالب (سفارشی‌ساز)</a> ';
	echo '<a class="button" href="' . esc_url( admin_url( 'nav-menus.php' ) ) . '">فهرست‌ها</a> ';
	echo '<a class="button" href="' . esc_url( admin_url( 'options-permalink.php' ) ) . '">پیوندهای یکتا (در صورت خطای ۴۰۴ یک‌بار ذخیره کنید)</a></p>';
	echo '</div>';
}

/**
 * List columns for entities.
 */
function sa_admin_columns_register() {
	foreach ( sa_entity_types() as $type ) {
		add_filter(
			'manage_' . $type . '_posts_columns',
			function ( $columns ) use ( $type ) {
				$new = array();
				foreach ( $columns as $k => $v ) {
					if ( 'title' === $k ) {
						$new['sa_thumb'] = 'تصویر';
						$new[ $k ]       = $v;
						if ( 'province' !== $type ) {
							$new['sa_parent'] = 'city' === $type ? 'استان' : ( 'travel_route' === $type ? 'شهرها' : 'شهر' );
						}
						$new['sa_gate'] = 'سطح ۷';
					} elseif ( 'date' === $k ) {
						$new[ $k ] = $v;
					} elseif ( 'author' === $k || 'comments' === $k ) {
						continue;
					} else {
						$new[ $k ] = $v;
					}
				}
				return $new;
			}
		);
		add_action(
			'manage_' . $type . '_posts_custom_column',
			function ( $column, $post_id ) use ( $type ) {
				switch ( $column ) {
					case 'sa_thumb':
						echo has_post_thumbnail( $post_id ) ? get_the_post_thumbnail( $post_id, array( 48, 48 ) ) : '<span class="sa-nothumb" title="بدون تصویر شاخص">—</span>';
						break;
					case 'sa_parent':
						if ( 'travel_route' === $type ) {
							$names = array_map( 'get_the_title', sa_meta_ids( $post_id, 'sa_city_ids' ) );
							echo esc_html( implode( '، ', array_slice( $names, 0, 4 ) ) . ( count( $names ) > 4 ? ' …' : '' ) );
						} else {
							$parent = sa_get_parent( $post_id, 'city' === $type ? 'province' : 'city' );
							if ( ! $parent ) {
								$pid    = (int) get_post_meta( $post_id, 'city' === $type ? 'sa_province_id' : 'sa_city_id', true );
								$parent = $pid ? get_post( $pid ) : null;
							}
							echo $parent ? '<a href="' . esc_url( get_edit_post_link( $parent ) ) . '">' . esc_html( get_the_title( $parent ) ) . '</a>' : '<span class="sa-missing">تعیین نشده</span>';
						}
						break;
					case 'sa_gate':
						echo sa_gate_badge( $post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						break;
				}
			},
			10,
			2
		);
	}
}
add_action( 'admin_init', 'sa_admin_columns_register' );

/**
 * Filter dropdown by province on entity lists.
 */
function sa_admin_filters() {
	global $typenow;
	if ( ! sa_is_entity( $typenow ) || 'province' === $typenow || ! taxonomy_exists( 'province_tax' ) ) {
		return;
	}
	$selected = isset( $_GET['province_tax'] ) ? sanitize_key( $_GET['province_tax'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	wp_dropdown_categories(
		array(
			'taxonomy'        => 'province_tax',
			'name'            => 'province_tax',
			'value_field'     => 'slug',
			'selected'        => $selected,
			'show_option_all' => 'همه‌ی استان‌ها',
			'hide_empty'      => false,
			'orderby'         => 'name',
		)
	);
}
add_action( 'restrict_manage_posts', 'sa_admin_filters' );

/**
 * Admin CSS/JS for meta boxes (inline; tiny).
 */
function sa_admin_assets( $hook ) {
	$screen = get_current_screen();
	$is_ours = $screen && ( in_array( $screen->post_type, sa_seo_post_types(), true ) || 'toplevel_page_sarzaminaryan' === $screen->id );
	if ( ! $is_ours ) {
		return;
	}
	wp_register_style( 'sa-admin', false, array(), SA_CHILD_VERSION );
	wp_enqueue_style( 'sa-admin' );
	wp_add_inline_style(
		'sa-admin',
		'.sa-box{padding:4px 0}.sa-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px 16px}.sa-grid__item--wide{grid-column:1/-1}.sa-grid label{display:block;font-weight:600;margin-bottom:4px}.sa-grid .description{display:block;font-weight:400;color:#646970}.sa-stack p{margin:0 0 12px}.sa-faq__row{display:grid;grid-template-columns:1fr auto;gap:6px 10px;align-items:start;border:1px solid #dcdcde;border-radius:6px;padding:10px;margin-bottom:10px;background:#fafafa}.sa-faq__row textarea{grid-column:1}.sa-faq__remove{grid-column:2;grid-row:1/3;align-self:center}.sa-badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;line-height:1.6}.sa-badge--ok{background:#d1fae5;color:#065f46}.sa-badge--warn{background:#fee2e2;color:#991b1b}.sa-badge--stale{background:#fef3c7;color:#92400e}.sa-missing{color:#b32d2e}.sa-count-hint{font-size:11px;color:#646970;display:block;margin-top:2px}.sa-count-hint.over{color:#b32d2e;font-weight:600}.sa-dash__cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px;margin:16px 0 24px}.sa-dash__card{display:flex;flex-direction:column;gap:4px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px;text-decoration:none;color:#1d2327}.sa-dash__card .dashicons{font-size:26px;width:26px;height:26px;color:#0e7490}.sa-dash__card em{color:#0e7490;font-style:normal;font-size:12px}.sa-filter{margin-bottom:4px}.column-sa_thumb{width:60px}.column-sa_gate{width:150px}'
	);
	wp_register_script( 'sa-admin', false, array(), SA_CHILD_VERSION, true );
	wp_enqueue_script( 'sa-admin' );
	wp_add_inline_script(
		'sa-admin',
		'(function(){' .
		'var rows=document.getElementById("sa-faq-rows"),add=document.getElementById("sa-faq-add"),tpl=document.getElementById("sa-faq-template");' .
		'if(rows&&add&&tpl){add.addEventListener("click",function(){rows.appendChild(tpl.content.cloneNode(true));});' .
		'rows.addEventListener("click",function(e){if(e.target.classList.contains("sa-faq__remove")){var r=e.target.closest(".sa-faq__row");if(rows.children.length>1){r.remove();}else{r.querySelectorAll("input,textarea").forEach(function(f){f.value="";});}}});}' .
		'document.querySelectorAll(".sa-count").forEach(function(el){var hint=document.createElement("span");hint.className="sa-count-hint";el.insertAdjacentElement("afterend",hint);var max=parseInt(el.dataset.max,10);function upd(){var n=el.value.length;hint.textContent=n+" / "+max+" کاراکتر";hint.classList.toggle("over",n>max);}el.addEventListener("input",upd);upd();});' .
		'document.querySelectorAll(".sa-filter").forEach(function(inp){var sel=document.getElementById(inp.dataset.target);if(!sel)return;inp.addEventListener("input",function(){var q=inp.value.trim().toLowerCase();Array.prototype.forEach.call(sel.options,function(o){o.hidden=q&&o.text.toLowerCase().indexOf(q)===-1;});});});' .
		'})();'
	);
}
add_action( 'admin_enqueue_scripts', 'sa_admin_assets' );

/**
 * Slug guidance notice on entity edit screens.
 */
function sa_admin_slug_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->base || ! sa_is_entity( $screen->post_type ) ) {
		return;
	}
	echo '<div class="notice notice-info"><p><strong>راهنما:</strong> نامک (slug) را انگلیسی، کوچک و با خط تیره بنویسید (مثلاً <code>naqsh-e-jahan-square</code>) — قانون سطح ۴ مدل داده. خلاصه را در «چکیده»، متن کامل را در ویرایشگر، و اطلاعات ساختاریافته را در جعبه‌های پایین وارد کنید. برای انتشار، همه‌ی الزامات سطح ۷ باید کامل باشد.</p></div>';
}
add_action( 'admin_notices', 'sa_admin_slug_notice' );

/**
 * Show excerpt box by default (it holds the summary field).
 */
add_filter(
	'default_hidden_meta_boxes',
	function ( $hidden, $screen ) {
		if ( sa_is_entity( $screen->post_type ) ) {
			$hidden = array_diff( $hidden, array( 'postexcerpt', 'slugdiv' ) );
		}
		return $hidden;
	},
	10,
	2
);

/**
 * Dashboard widget with content status.
 */
add_action(
	'wp_dashboard_setup',
	function () {
		wp_add_dashboard_widget(
			'sa_dashboard_widget',
			'سرزمین آریان — وضعیت محتوا',
			function () {
				echo '<ul>';
				foreach ( sa_entity_types() as $type ) {
					$c = wp_count_posts( $type );
					printf( '<li><a href="%s">%s</a>: %s منتشرشده، %s پیش‌نویس</li>', esc_url( admin_url( 'edit.php?post_type=' . $type ) ), esc_html( sa_entity_label( $type, true ) ), esc_html( sa_fa_digits( (int) $c->publish ) ), esc_html( sa_fa_digits( (int) $c->draft ) ) );
				}
				echo '</ul><p><a href="' . esc_url( admin_url( 'admin.php?page=sarzaminaryan' ) ) . '">داشبورد کامل ←</a></p>';
			}
		);
	}
);

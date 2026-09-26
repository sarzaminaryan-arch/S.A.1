<?php
/**
 * Meta boxes: entity fields (Level 1), relations (Level 3), SEO (Level 5), FAQ + sources (Level 7).
 *
 * Meta keys: sa_{field} · relations sa_{target}_id / sa_{target}_ids (multi-row) ·
 * SEO sa_seo_title, sa_seo_description, sa_focus_keyword, sa_og_title, sa_og_description,
 * sa_canonical_url, sa_noindex · FAQ sa_faq (JSON) · sources sa_sources.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Post types that get SEO + FAQ boxes.
 *
 * @return string[]
 */
function sa_seo_post_types() {
	return array_merge( array( 'post', 'page' ), sa_entity_types() );
}

/**
 * Register boxes.
 */
function sa_add_meta_boxes() {
	foreach ( sa_entity_types() as $type ) {
		$e = sa_entities_config()[ $type ];
		if ( ! empty( $e['fields'] ) ) {
			add_meta_box( 'sa-fields', 'اطلاعات ساختاریافته‌ی ' . $e['singular'], 'sa_render_fields_box', $type, 'normal', 'high' );
		}
		add_meta_box( 'sa-relations', 'روابط (' . $e['singular'] . ' به کجا تعلق دارد؟)', 'sa_render_relations_box', $type, 'side', 'high' );
	}
	foreach ( sa_seo_post_types() as $type ) {
		add_meta_box( 'sa-seo', 'سئو و شبکه‌های اجتماعی', 'sa_render_seo_box', $type, 'normal', 'default' );
		add_meta_box( 'sa-faq', 'سوالات متداول (FAQ) — برای اسکیمای FAQPage', 'sa_render_faq_box', $type, 'normal', 'default' );
		add_meta_box( 'sa-sources', 'منابع و تاریخ به‌روزرسانی اطلاعات', 'sa_render_sources_box', $type, 'normal', 'low' );
	}
}
add_action( 'add_meta_boxes', 'sa_add_meta_boxes' );

/**
 * Nonce (printed once per screen).
 */
function sa_meta_nonce_field() {
	static $done = false;
	if ( ! $done ) {
		wp_nonce_field( 'sa_save_meta', 'sa_meta_nonce' );
		$done = true;
	}
}

/**
 * Entity fields box.
 *
 * @param WP_Post $post Post.
 */
function sa_render_fields_box( $post ) {
	sa_meta_nonce_field();
	$e = sa_entities_config()[ $post->post_type ];
	echo '<div class="sa-box sa-grid">';
	foreach ( $e['fields'] as $f ) {
		$value = get_post_meta( $post->ID, $f['key'], true );
		$id    = esc_attr( $f['key'] );
		$label = esc_html( $f['label'] );
		$wide  = in_array( $f['type'], array( 'textarea', 'list' ), true ) ? ' sa-grid__item--wide' : '';
		echo '<div class="sa-grid__item' . $wide . '">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<label for="' . $id . '">' . $label . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		switch ( $f['type'] ) {
			case 'textarea':
			case 'list':
				printf( '<textarea id="%1$s" name="%1$s" rows="3" class="widefat">%2$s</textarea>', $id, esc_textarea( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'integer':
				printf( '<input type="number" step="1" id="%1$s" name="%1$s" value="%2$s" class="widefat" inputmode="numeric" dir="ltr"%3$s%4$s>', $id, esc_attr( $value ), isset( $f['min'] ) ? ' min="' . (int) $f['min'] . '"' : '', isset( $f['max'] ) ? ' max="' . (int) $f['max'] . '"' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'number':
			case 'float':
				printf( '<input type="number" step="any" id="%1$s" name="%1$s" value="%2$s" class="widefat" inputmode="decimal" dir="ltr">', $id, esc_attr( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'url':
				printf( '<input type="url" id="%1$s" name="%1$s" value="%2$s" class="widefat" dir="ltr" placeholder="https://">', $id, esc_url( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'boolean':
				printf( '<label class="sa-check"><input type="checkbox" id="%1$s" name="%1$s" value="1" %2$s> بله</label>', $id, checked( $value, '1', false ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'time':
				printf( '<input type="time" id="%1$s" name="%1$s" value="%2$s" class="widefat" dir="ltr">', $id, esc_attr( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				break;
			case 'date':
				printf( '<input type="date" id="%1$s" name="%1$s" value="%2$s" class="widefat" dir="ltr" placeholder="YYYY-MM-DD">', $id, esc_attr( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				if ( $value && strtotime( $value ) ) {
					$age = (int) floor( ( time() - strtotime( $value ) ) / DAY_IN_SECONDS );
					echo '<span class="description">' . esc_html( sa_jalali_date( 'j F Y', strtotime( $value ) ) ) . ' — ' . esc_html( sa_fa_digits( $age ) ) . ' روز پیش' . ( $age > sa_stale_after_days() ? ' · <strong class="sa-missing">نیازمند بازبینی</strong>' : '' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				} elseif ( 'sa_last_verified_date' === $f['key'] ) {
					echo '<span class="description">تاریخ میلادی؛ بعد از هر بار بررسی ساعات بازدید/قیمت/دسترسی به‌روز کنید.</span>';
				}
				break;
			case 'reference':
				sa_posts_dropdown( $f['key'], $f['target'], (array) $value, false );
				break;
			default:
				printf( '<input type="text" id="%1$s" name="%1$s" value="%2$s" class="widefat">', $id, esc_attr( $value ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		if ( ! empty( $f['unit'] ) ) {
			echo '<span class="description">واحد: ' . esc_html( $f['unit'] ) . '</span>';
		}
		echo '</div>';
	}
	echo '</div>';
	echo '<p class="description">خلاصه‌ی کوتاه را در «چکیده» و متن کامل معرفی را در ویرایشگر اصلی بنویسید. تصویر شاخص، اسلاگ (نامک انگلیسی) و طبقه‌بندی‌ها از جعبه‌های خودشان تنظیم می‌شوند.</p>';
}

/**
 * Dropdown of posts of a type (single or multiple).
 *
 * @param string $name     Field name.
 * @param string $type     Target CPT.
 * @param int[]  $selected Selected IDs.
 * @param bool   $multiple Multiple?
 */
function sa_posts_dropdown( $name, $type, $selected, $multiple ) {
	$selected = array_map( 'absint', $selected );
	$posts    = get_posts(
		array(
			'post_type'        => $type,
			'post_status'      => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page'   => 1500,
			'orderby'          => 'title',
			'order'            => 'ASC',
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);
	$attr_name = $multiple ? esc_attr( $name ) . '[]' : esc_attr( $name );
	if ( $multiple ) {
		printf( '<input type="search" class="widefat sa-filter" placeholder="فیلتر…" data-target="%s" aria-label="فیلتر فهرست">', esc_attr( $name ) );
	}
	printf( '<select id="%s" name="%s" class="widefat%s"%s>', esc_attr( $name ), $attr_name, $multiple ? ' sa-multi' : '', $multiple ? ' multiple size="8"' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	if ( ! $multiple ) {
		echo '<option value="">— انتخاب کنید —</option>';
	}
	foreach ( $posts as $p ) {
		$suffix = '';
		if ( in_array( $type, array( 'city', 'attraction' ), true ) ) {
			$prov = (int) get_post_meta( $p->ID, 'sa_province_id', true );
			if ( $prov ) {
				$suffix = ' (' . get_the_title( $prov ) . ')';
			}
		}
		if ( 'publish' !== $p->post_status ) {
			$suffix .= ' [پیش‌نویس]';
		}
		printf( '<option value="%d"%s>%s</option>', $p->ID, selected( in_array( $p->ID, $selected, true ), true, false ), esc_html( $p->post_title . $suffix ) );
	}
	echo '</select>';
	if ( empty( $posts ) ) {
		printf( '<p class="description">هنوز هیچ «%s» ثبت نشده است.</p>', esc_html( sa_entity_label( $type ) ) );
	}
}

/**
 * Relations box.
 *
 * @param WP_Post $post Post.
 */
function sa_render_relations_box( $post ) {
	sa_meta_nonce_field();
	$e = sa_entities_config()[ $post->post_type ];
	echo '<div class="sa-box sa-stack">';
	$has_city = false;
	foreach ( $e['relations'] as $r ) {
		if ( 'active' !== $r['status'] && ! SA_ENABLE_ACCOMMODATION ) {
			continue;
		}
		if ( 'has_many' === $r['type'] || empty( $r['key'] ) ) {
			continue;
		}
		// Inverse side of belongs_to_many (city/attraction → travel_route) is computed; skip.
		if ( 'belongs_to_many' === $r['type'] && 'travel_route' === $r['target'] ) {
			continue;
		}
		$label = sa_entity_label( $r['target'] );
		if ( 'belongs_to' === $r['type'] ) {
			if ( 'city' === $r['target'] ) {
				$has_city = true;
			}
			$disabled = ( 'province' === $r['target'] && $has_city ) ? ' <span class="description">(به‌صورت خودکار از شهر پر می‌شود)</span>' : '';
			echo '<p><label for="' . esc_attr( $r['key'] ) . '"><strong>' . esc_html( $label ) . '</strong>' . $disabled . '</label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			sa_posts_dropdown( $r['key'], $r['target'], (array) get_post_meta( $post->ID, $r['key'], true ), false );
			echo '</p>';
		} elseif ( in_array( $r['type'], array( 'belongs_to_many', 'related_many', 'near_many' ), true ) ) {
			$title = 'related_many' === $r['type'] ? sa_entity_label( $r['target'], true ) . ' مشابه' : sa_entity_label( $r['target'], true ) . ' این ' . $e['singular'];
			echo '<p><label for="' . esc_attr( $r['key'] ) . '"><strong>' . esc_html( $title ) . '</strong></label>';
			sa_posts_dropdown( $r['key'], $r['target'], sa_meta_ids( $post->ID, $r['key'] ), true );
			echo '<span class="description">با نگه‌داشتن Ctrl/⌘ چند مورد انتخاب کنید.</span></p>';
		}
	}
	if ( 'province' === $post->post_type ) {
		echo '<p class="description">استان با ترم هم‌نام در طبقه‌بندی «استان‌ها» پیوند می‌خورد (نامک انگلیسی باید یکسان باشد؛ مثلاً isfahan).</p>';
	}
	echo '</div>';
}

/**
 * SEO box.
 *
 * @param WP_Post $post Post.
 */
function sa_render_seo_box( $post ) {
	sa_meta_nonce_field();
	$g = function ( $k ) use ( $post ) {
		return get_post_meta( $post->ID, $k, true );
	};
	?>
	<div class="sa-box sa-grid">
		<div class="sa-grid__item sa-grid__item--wide">
			<label for="sa_seo_title">عنوان سئو <span class="description">(حداکثر ۶۰ کاراکتر — خالی = عنوان خودکار)</span></label>
			<input type="text" id="sa_seo_title" name="sa_seo_title" class="widefat sa-count" maxlength="70" data-max="60" value="<?php echo esc_attr( $g( 'sa_seo_title' ) ); ?>">
		</div>
		<div class="sa-grid__item sa-grid__item--wide">
			<label for="sa_seo_description">توضیحات متا <span class="description">(۷۰ تا ۱۵۵ کاراکتر)</span></label>
			<textarea id="sa_seo_description" name="sa_seo_description" class="widefat sa-count" rows="2" maxlength="170" data-max="155"><?php echo esc_textarea( $g( 'sa_seo_description' ) ); ?></textarea>
		</div>
		<div class="sa-grid__item">
			<label for="sa_focus_keyword">کلیدواژه‌ی کانونی</label>
			<input type="text" id="sa_focus_keyword" name="sa_focus_keyword" class="widefat" value="<?php echo esc_attr( $g( 'sa_focus_keyword' ) ); ?>">
		</div>
		<div class="sa-grid__item">
			<label for="sa_canonical_url">آدرس کنونیکال <span class="description">(فقط اگر متفاوت است)</span></label>
			<input type="url" id="sa_canonical_url" name="sa_canonical_url" class="widefat" dir="ltr" value="<?php echo esc_url( $g( 'sa_canonical_url' ) ); ?>">
		</div>
		<div class="sa-grid__item">
			<label for="sa_og_title">عنوان اشتراک‌گذاری (OG)</label>
			<input type="text" id="sa_og_title" name="sa_og_title" class="widefat" value="<?php echo esc_attr( $g( 'sa_og_title' ) ); ?>">
		</div>
		<div class="sa-grid__item">
			<label for="sa_og_description">توضیح اشتراک‌گذاری (OG)</label>
			<input type="text" id="sa_og_description" name="sa_og_description" class="widefat" value="<?php echo esc_attr( $g( 'sa_og_description' ) ); ?>">
		</div>
		<div class="sa-grid__item sa-grid__item--wide">
			<label class="sa-check"><input type="checkbox" name="sa_noindex" value="1" <?php checked( $g( 'sa_noindex' ), '1' ); ?>> این صفحه در موتورهای جست‌وجو ایندکس نشود (noindex)</label>
		</div>
	</div>
	<?php
}

/**
 * FAQ repeater.
 *
 * @param WP_Post $post Post.
 */
function sa_render_faq_box( $post ) {
	sa_meta_nonce_field();
	$faq = sa_get_faq( $post->ID );
	if ( empty( $faq ) ) {
		$faq = array( array( 'q' => '', 'a' => '' ) );
	}
	echo '<div class="sa-box sa-faq" id="sa-faq-rows">';
	foreach ( $faq as $row ) {
		sa_faq_row_html( $row['q'], $row['a'] );
	}
	echo '</div>';
	echo '<template id="sa-faq-template">';
	sa_faq_row_html( '', '' );
	echo '</template>';
	echo '<p><button type="button" class="button" id="sa-faq-add">+ افزودن پرسش</button> <span class="description">حداقل ۳ پرسش واقعی کاربران، پاسخ حداکثر ۶۰ کلمه.</span></p>';
}

/**
 * One FAQ row.
 *
 * @param string $q Question.
 * @param string $a Answer.
 */
function sa_faq_row_html( $q, $a ) {
	?>
	<div class="sa-faq__row">
		<input type="text" name="sa_faq_q[]" class="widefat" placeholder="پرسش…" value="<?php echo esc_attr( $q ); ?>">
		<textarea name="sa_faq_a[]" class="widefat" rows="2" placeholder="پاسخ…"><?php echo esc_textarea( $a ); ?></textarea>
		<button type="button" class="button-link-delete sa-faq__remove" aria-label="حذف این پرسش">حذف</button>
	</div>
	<?php
}

/**
 * Sources box.
 *
 * @param WP_Post $post Post.
 */
function sa_render_sources_box( $post ) {
	sa_meta_nonce_field();
	$min = sa_content_minimums( $post->post_type );
	printf( '<textarea name="sa_sources" class="widefat" rows="6" dir="auto" placeholder="عنوان منبع | سازمان منتشرکننده | https://… | تاریخ دسترسی">%s</textarea>', esc_textarea( get_post_meta( $post->ID, 'sa_sources', true ) ) );
	echo '<p class="description">هر منبع در یک خط با قالب <code>عنوان | سازمان | URL | تاریخ دسترسی</code>. حداقل برای انتشار: <strong>' . esc_html( sa_fa_digits( (int) $min['sources'] ) ) . '</strong> منبع دارای لینک. هرچه بعد از یک خط <code>---</code> بنویسید (مثلاً FACT CHECK REPORT ایجنت) خصوصی می‌ماند و در سایت نمایش داده نمی‌شود.</p>';
	printf( '<p><label>تاریخ آخرین بازبینی اطلاعات (مثلاً قیمت‌ها و ساعات): <input type="date" name="sa_facts_checked" dir="ltr" value="%s"></label></p>', esc_attr( get_post_meta( $post->ID, 'sa_facts_checked', true ) ) );
}

/**
 * Sanitize one value by field type.
 *
 * @param mixed  $value Raw.
 * @param string $type  Field type.
 * @return string
 */
function sa_sanitize_field( $value, $type ) {
	$value = is_string( $value ) ? wp_unslash( $value ) : $value;
	switch ( $type ) {
		case 'integer':
			$value = sa_en_digits( $value );
			return '' === trim( (string) $value ) ? '' : (string) (int) $value;
		case 'number':
		case 'float':
			$value = str_replace( ',', '', sa_en_digits( $value ) );
			return is_numeric( $value ) ? (string) (float) $value : '';
		case 'url':
			return esc_url_raw( trim( (string) $value ) );
		case 'boolean':
			return empty( $value ) ? '' : '1';
		case 'time':
			return preg_match( '/^\d{2}:\d{2}$/', (string) $value ) ? $value : '';
		case 'date':
			$value = sa_en_digits( trim( (string) $value ) );
			return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) && strtotime( $value ) ? $value : '';
		case 'textarea':
		case 'list':
			return sanitize_textarea_field( (string) $value );
		case 'reference':
			return (string) absint( $value );
		default:
			return sanitize_text_field( (string) $value );
	}
}

/**
 * Save handler.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post.
 */
function sa_save_meta( $post_id, $post ) {
	if ( ! isset( $_POST['sa_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['sa_meta_nonce'] ), 'sa_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$type = $post->post_type;

	// 1. Entity fields.
	if ( sa_is_entity( $type ) ) {
		$e = sa_entities_config()[ $type ];
		foreach ( $e['fields'] as $f ) {
			$raw   = isset( $_POST[ $f['key'] ] ) ? $_POST[ $f['key'] ] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean = sa_sanitize_field( $raw, $f['type'] );
			if ( '' === $clean ) {
				delete_post_meta( $post_id, $f['key'] );
			} else {
				update_post_meta( $post_id, $f['key'], $clean );
			}
		}

		// 2. Relations.
		foreach ( $e['relations'] as $r ) {
			if ( empty( $r['key'] ) || 'has_many' === $r['type'] ) {
				continue;
			}
			if ( 'belongs_to_many' === $r['type'] && 'travel_route' === $r['target'] ) {
				continue; // computed inverse.
			}
			if ( 'belongs_to' === $r['type'] ) {
				$val = isset( $_POST[ $r['key'] ] ) ? absint( $_POST[ $r['key'] ] ) : 0;
				if ( $val && get_post_type( $val ) === $r['target'] ) {
					update_post_meta( $post_id, $r['key'], $val );
				} else {
					delete_post_meta( $post_id, $r['key'] );
				}
			} else {
				$vals = isset( $_POST[ $r['key'] ] ) ? array_map( 'absint', (array) $_POST[ $r['key'] ] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$vals = array_values( array_unique( array_filter( $vals ) ) );
				delete_post_meta( $post_id, $r['key'] );
				foreach ( $vals as $v ) {
					if ( $v !== $post_id && get_post_type( $v ) === $r['target'] ) {
						add_post_meta( $post_id, $r['key'], $v );
					}
				}
			}
		}

		// 3. Integrity (R1) + taxonomy sync.
		sa_sync_relations( $post_id, $type );
	}

	// 4. SEO fields (all supported types).
	if ( in_array( $type, sa_seo_post_types(), true ) ) {
		$seo = array(
			'sa_seo_title'       => 'text',
			'sa_seo_description' => 'textarea',
			'sa_focus_keyword'   => 'text',
			'sa_og_title'        => 'text',
			'sa_og_description'  => 'text',
			'sa_canonical_url'   => 'url',
			'sa_noindex'         => 'boolean',
			'sa_sources'         => 'textarea',
			'sa_facts_checked'   => 'text',
		);
		foreach ( $seo as $key => $ftype ) {
			$raw   = isset( $_POST[ $key ] ) ? $_POST[ $key ] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$clean = sa_sanitize_field( $raw, $ftype );
			if ( '' === $clean ) {
				delete_post_meta( $post_id, $key );
			} else {
				update_post_meta( $post_id, $key, $clean );
			}
		}

		// 5. FAQ.
		$qs  = isset( $_POST['sa_faq_q'] ) ? (array) wp_unslash( $_POST['sa_faq_q'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$as  = isset( $_POST['sa_faq_a'] ) ? (array) wp_unslash( $_POST['sa_faq_a'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$faq = array();
		foreach ( $qs as $i => $q ) {
			$q = sanitize_text_field( $q );
			$a = isset( $as[ $i ] ) ? sanitize_textarea_field( $as[ $i ] ) : '';
			if ( '' !== $q && '' !== $a ) {
				$faq[] = array(
					'q' => $q,
					'a' => $a,
				);
			}
		}
		if ( $faq ) {
			update_post_meta( $post_id, 'sa_faq', wp_json_encode( $faq, JSON_UNESCAPED_UNICODE ) );
		} else {
			delete_post_meta( $post_id, 'sa_faq' );
		}
	}

	sa_flush_relation_cache( $post_id, $type );
}
add_action( 'save_post', 'sa_save_meta', 10, 2 );

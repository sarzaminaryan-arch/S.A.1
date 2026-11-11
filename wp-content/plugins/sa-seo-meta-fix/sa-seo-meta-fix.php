<?php
/**
 * Plugin Name:       اصلاح یک‌بارهٔ متادیتای سئو (فاز ۳)
 * Plugin URI:        https://github.com/sarzaminaryan-arch/S.A.1/releases/tag/seo-meta-fix-v1.0.0
 * Description:       افزونهٔ یک‌بارمصرف برای همگام‌سازی عنوان و متادیتای سئوی شش راهنمای طبیعت‌گردی با مقادیر بازنویسی‌شدهٔ مخزن. فقط عنوان نوشته، sa_seo_title و sa_seo_description را با نگهبان «مقدار مورد انتظار» تغییر می‌دهد؛ به متن، تصویر، FAQ و سایر فیلدها دست نمی‌زند.
 * Version:           1.0.0
 * Author:            Sarzaminaryan
 * Author URI:        https://sarzaminaryan.ir/
 * Text Domain:       sa-seo-meta-fix
 * Requires at least: 6.0
 * Requires PHP:      7.4
 *
 * @package SA_SEO_Meta_Fix
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SA_SEO_Meta_Fix {

	const VERSION         = '1.0.0';
	const BACKUP_OPTION   = 'sa_seo_meta_fix_20261111_backup';
	const REPORT_OPTION   = 'sa_seo_meta_fix_20261111_report';
	const ROLLBACK_OPTION = 'sa_seo_meta_fix_20261111_rollback_report';
	const PAGE_SLUG       = 'sa-seo-meta-fix';

	/**
	 * The six records from docs/live-site/seo-meta-fix-list.md.
	 *
	 * A field is changed only when the live value is still exactly the value
	 * observed by the 2026-11-11 audit («expected»). If the owner has changed
	 * it since then, the field is skipped instead of being overwritten.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private static function fixes() {
		return array(
			array(
				'slug' => 'diy-bottle-lantern',
				'fields' => array(
					array(
						'label' => 'عنوان سئو (sa_seo_title)',
						'type' => 'meta',
						'target' => 'ساخت فانوس با بطری آب | پخش نور چراغ در کمپ',
						'expected' => 'ساخت فانوس با بطری آب | روشنایی اردوگاه بدون برق',
						'key' => 'sa_seo_title',
					),
					array(
						'label' => 'توضیح سئو (sa_seo_description)',
						'type' => 'meta',
						'target' => 'ساخت فانوس با بطری آب و چراغ LED: آماده‌سازی بطری بسته، بستن چراغ سر رو به آب و تنظیم بازتابنده؛ بدون شعله، آویز سنگین یا وعدهٔ نور بیشتر.',
						'expected' => 'یک بطری آب و یک چراغ‌قوه، نور نقطه‌ای را به روشنایی ملایم همه‌طرفه تبدیل می‌کند: فانوس میزی، نسخهٔ آویز، نور گرم‌تر و هشدارهای آتش.',
						'key' => 'sa_seo_description',
					),
				),
			),
			array(
				'slug' => 'diy-can-stove',
				'fields' => array(
					array(
						'label' => 'عنوان نوشته',
						'type' => 'post_title',
						'target' => 'ساخت اجاق با قوطی کنسرو؛ خطرها را پیش از روشن‌کردن بشناس',
						'expected' => 'ساخت اجاق با قوطی کنسرو؛ چطور با یه آشغال فلزی، غذای گرم بپزیم؟',
					),
					array(
						'label' => 'عنوان سئو (sa_seo_title)',
						'type' => 'meta',
						'target' => 'اجاق قوطی دست‌ساز؛ خطرها و گزینهٔ امن‌تر برای کمپ',
						'expected' => 'ساخت اجاق با قوطی کنسرو | آموزش ساخت اجاق هوبو در طبیعت',
						'key' => 'sa_seo_title',
					),
					array(
						'label' => 'توضیح سئو (sa_seo_description)',
						'type' => 'meta',
						'target' => 'قوطی کنسرو را برای ساخت اجاق نسوزان؛ خطر لبهٔ تیز، پوشش ناشناخته، آتش و مونوکسیدکربن را بشناس و گزینهٔ امن‌تر کمپ را انتخاب کن.',
						'expected' => 'گازت تموم شده و نیاز به یه غذای گرم داری؟ تو این مقاله یاد می‌گیری چطور با یه قوطی کنسرو خالی و چند تا شاخه خشک، یه اجاق هیزمی کارآمد و حیاتی بسازی.',
						'key' => 'sa_seo_description',
					),
				),
			),
			array(
				'slug' => 'diy-water-filter',
				'fields' => array(
					array(
						'label' => 'عنوان نوشته',
						'type' => 'post_title',
						'target' => 'ساخت فیلتر آب با بطری و زغال؛ زلالی آب، ایمنی نمی‌آورد',
						'expected' => 'ساخت فیلتر آب با بطری و زغال؛ چطور تو شرایط اضطراری آب رو تصفیه کنیم؟',
					),
					array(
						'label' => 'عنوان سئو (sa_seo_title)',
						'type' => 'meta',
						'target' => 'فیلتر آب دست‌ساز با بطری و زغال؛ زلالی به معنی سلامت نیست',
						'expected' => 'ساخت فیلتر آب با بطری و زغال | تصفیه آب اضطراری در طبیعت',
						'key' => 'sa_seo_title',
					),
					array(
						'label' => 'توضیح سئو (sa_seo_description)',
						'type' => 'meta',
						'target' => 'مدل نمایشی بطری، پارچه، ماسه و سنگ‌ریزه فقط می‌تواند بخشی از ذرات را جدا کند؛ زغال معمولی آب را ضدعفونی نمی‌کند و خروجی فیلتر دست‌ساز آب آشامیدنی نیست.',
						'expected' => 'وسط طبیعت فیلتر آب نداری یا تموم شده؟ تو این مقاله یاد می‌گیری چطور با یه بطری پلاستیکی، زغال و ماسه، یه فیلتر آب اضطراری بسازی و آب رو برای نوشیدن آماده کنی.',
						'key' => 'sa_seo_description',
					),
				),
			),
			array(
				'slug' => 'gps-navigation-tools',
				'fields' => array(
					array(
						'label' => 'عنوان نوشته',
						'type' => 'post_title',
						'target' => 'ابزارهای مسیریابی در طبیعت؛ GPS، نقشه و قطب‌نما',
						'expected' => 'معرفی GPS و ابزار مسیریابی؛ چطور تو دل طبیعت گم نشیم؟',
					),
					array(
						'label' => 'عنوان سئو (sa_seo_title)',
						'type' => 'meta',
						'target' => 'ابزار مسیریابی در طبیعت | GPS، نقشه و قطب‌نما',
						'expected' => 'معرفی GPS و ابزار مسیریابی | چطور تو طبیعت گم نشیم؟',
						'key' => 'sa_seo_title',
					),
					array(
						'label' => 'توضیح سئو (sa_seo_description)',
						'type' => 'meta',
						'target' => 'راهنمای استفادهٔ پشتیبان‌دار از GPS دستی، نقشهٔ آفلاین گوشی، نقشهٔ کاغذی و قطب‌نما؛ همراه با نکات ایمنی وقتی مسیر را گم می‌کنی.',
						'expected' => 'نمی‌دونی تو طبیعت چطور مسیر رو پیدا کنی؟ تو این مقاله انواع GPS دستی، اپلیکیشن‌های موبایل و قطب‌نما رو بررسی کردیم تا تو کوه و جنگل مسیر رو گم نکنی.',
						'key' => 'sa_seo_description',
					),
				),
			),
			array(
				'slug' => 'multi-tool-knife-review',
				'fields' => array(
					array(
						'label' => 'عنوان نوشته',
						'type' => 'post_title',
						'target' => 'چاقوهای چندکارهٔ طبیعت‌گردی؛ چطور ابزار مناسب را انتخاب کنیم؟',
						'expected' => 'معرفی چاقوهای چندکاره؛ آچار فرانسه طبیعت‌گردی رو چطور انتخاب کنیم؟',
					),
					array(
						'label' => 'عنوان سئو (sa_seo_title)',
						'type' => 'meta',
						'target' => 'چاقوهای چندکارهٔ کمپینگ | راهنمای انتخاب و ایمنی',
						'expected' => 'معرفی چاقوهای چندکاره | ابزار همه‌کارهٔ طبیعت‌گردی',
						'key' => 'sa_seo_title',
					),
					array(
						'label' => 'توضیح سئو (sa_seo_description)',
						'type' => 'meta',
						'target' => 'مقایسهٔ چاقوی جیبی چندابزاره، ابزار انبردستی و تیغه‌ثابت؛ معیارهای انتخاب، نگهداری و استفادهٔ ایمن در طبیعت‌گردی.',
						'expected' => 'دنبال بهترین چاقوی چندکاره برای طبیعت‌گردی هستی؟ تو این مقاله انواع مولتی‌تول، چاقوی سوئیسی و ابزارهای بقا رو بررسی کردیم تا تو شرایط اضطراری کم نیاری.',
						'key' => 'sa_seo_description',
					),
				),
			),
			array(
				'slug' => 'outdoor-clothing-guide',
				'fields' => array(
					array(
						'label' => 'توضیح سئو (sa_seo_description)',
						'type' => 'meta',
						'target' => 'لباس مناسب طبیعت را با لایهٔ پایه، گرم و محافظ انتخاب کن؛ تفاوت بادگیر و بارانی، لباس خشک یدک و نشانه‌های خطر سرما و گرما را بشناس.',
						'expected' => 'نمی‌دونی تو طبیعت چی بپوشی؟ تو این مقاله سیستم لایه‌لایه لباس پوشیدن رو یاد می‌گیری که تو هر فصل و هر هوایی، نه سردت بشه نه گرم، نه خیس بشی نه عرق‌سوز.',
						'key' => 'sa_seo_description',
					),
				),
			),
		);
	}

	public static function boot() {
		register_activation_hook( __FILE__, array( __CLASS__, 'activate' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_post_sa_seo_meta_fix', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_notice' ) );
	}

	public static function activate() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		self::apply_changes( 'activation' );
	}

	public static function register_menu() {
		add_management_page(
			'اصلاح یک‌بارهٔ متادیتای سئو',
			'اصلاح متای سئو (یک‌باره)',
			'manage_options',
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Apply the guarded updates and save an auditable report + backup.
	 *
	 * @param string $source activation|manual.
	 * @return array<string,mixed>
	 */
	public static function apply_changes( $source = 'manual' ) {
		$backup = get_option( self::BACKUP_OPTION, array() );
		if ( ! is_array( $backup ) ) {
			$backup = array();
		}
		$backup_dirty = false;

		$report = array(
			'source'  => (string) $source,
			'time'    => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'counts'  => array(
				'updated'         => 0,
				'already_target'  => 0,
				'skipped_changed' => 0,
				'not_found'       => 0,
				'failed'          => 0,
			),
			'records' => array(),
		);

		foreach ( self::fixes() as $fix ) {
			$slug       = (string) $fix['slug'];
			$post       = self::find_post( $slug );
			$record_log = array(
				'slug'    => $slug,
				'post_id' => $post ? (int) $post->ID : 0,
				'fields'  => array(),
			);

			if ( ! $post ) {
				++$report['counts']['not_found'];
				$record_log['status'] = 'not_found';
				$report['records'][]  = $record_log;
				continue;
			}

			if ( ! isset( $backup[ $slug ] ) ) {
				$backup[ $slug ] = array(
					'post_id'    => (int) $post->ID,
					'post_title' => (string) $post->post_title,
					'meta'       => array(
						'sa_seo_title'       => (string) get_post_meta( $post->ID, 'sa_seo_title', true ),
						'sa_seo_description' => (string) get_post_meta( $post->ID, 'sa_seo_description', true ),
					),
				);
				$backup_dirty = true;
			}

			foreach ( $fix['fields'] as $field ) {
				$label    = (string) $field['label'];
				$target   = (string) $field['target'];
				$expected = (string) $field['expected'];
				$before   = self::field_value( $post, $field );
				$status   = '';
				$after    = $before;

				if ( $before === $target ) {
					$status = 'already_target';
				} elseif ( $before !== $expected ) {
					$status = 'skipped_changed';
				} else {
					$ok = self::write_field( $post, $field, $target );
					// Re-read from the database/cache after the write.
					$fresh = get_post( $post->ID );
					$after = $fresh ? self::field_value( $fresh, $field ) : '';
					$status = ( $ok && $after === $target ) ? 'updated' : 'failed';
				}

				if ( isset( $report['counts'][ $status ] ) ) {
					++$report['counts'][ $status ];
				}
				$record_log['fields'][] = array(
					'label'  => $label,
					'before' => $before,
					'target' => $target,
					'after'  => $after,
					'status' => $status,
				);
			}

			$report['records'][] = $record_log;
		}

		if ( $backup_dirty ) {
			// Autoload off: this is a one-time safety copy, not runtime data.
			add_option( self::BACKUP_OPTION, $backup, '', 'no' );
			if ( false === get_option( self::BACKUP_OPTION, false ) ) {
				update_option( self::BACKUP_OPTION, $backup );
			}
		}
		update_option( self::REPORT_OPTION, $report, false );
		return $report;
	}

	/**
	 * Restore the values captured before the first run.
	 *
	 * A field is restored only while it still holds either the target value
	 * written by this plugin or the original backup value, so edits made by
	 * the owner after the fix are never reverted silently.
	 *
	 * @return array<string,mixed>
	 */
	public static function rollback() {
		$backup = get_option( self::BACKUP_OPTION, array() );
		$report = array(
			'time'    => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'counts'  => array(
				'restored'        => 0,
				'already_backup'  => 0,
				'skipped_changed' => 0,
				'not_found'       => 0,
				'failed'          => 0,
			),
			'records' => array(),
		);

		if ( ! is_array( $backup ) || ! $backup ) {
			$report['error'] = 'no_backup';
			update_option( self::ROLLBACK_OPTION, $report, false );
			return $report;
		}

		foreach ( self::fixes() as $fix ) {
			$slug = (string) $fix['slug'];
			if ( empty( $backup[ $slug ] ) ) {
				continue;
			}
			$post       = self::find_post( $slug );
			$record_log = array(
				'slug'    => $slug,
				'post_id' => $post ? (int) $post->ID : 0,
				'fields'  => array(),
			);
			if ( ! $post || (int) $backup[ $slug ]['post_id'] !== (int) $post->ID ) {
				++$report['counts']['not_found'];
				$record_log['status'] = 'not_found';
				$report['records'][]  = $record_log;
				continue;
			}

			foreach ( $fix['fields'] as $field ) {
				$label   = (string) $field['label'];
				$target  = (string) $field['target'];
				$restore = self::backup_field_value( $backup[ $slug ], $field );
				$before  = self::field_value( $post, $field );
				$after   = $before;
				$status  = '';

				if ( $before === $restore ) {
					$status = 'already_backup';
				} elseif ( $before !== $target ) {
					$status = 'skipped_changed';
				} else {
					$ok    = self::write_field( $post, $field, $restore, true );
					$fresh = get_post( $post->ID );
					$after = $fresh ? self::field_value( $fresh, $field ) : '';
					$status = ( $ok && $after === $restore ) ? 'restored' : 'failed';
				}

				if ( isset( $report['counts'][ $status ] ) ) {
					++$report['counts'][ $status ];
				}
				$record_log['fields'][] = array(
					'label'  => $label,
					'before' => $before,
					'target' => $restore,
					'after'  => $after,
					'status' => $status,
				);
			}
			$report['records'][] = $record_log;
		}

		update_option( self::ROLLBACK_OPTION, $report, false );
		return $report;
	}

	private static function find_post( $slug ) {
		$posts = get_posts(
			array(
				'name'              => $slug,
				'post_type'         => 'post',
				'post_status'       => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'    => 1,
				'no_found_rows'     => true,
				'suppress_filters'  => false,
				'ignore_sticky_posts' => true,
			)
		);
		if ( ! $posts ) {
			return null;
		}
		$post = $posts[0];
		return ( $post && $slug === (string) $post->post_name ) ? $post : null;
	}

	/**
	 * @param WP_Post              $post Post.
	 * @param array<string,string> $field Field definition.
	 */
	private static function field_value( $post, $field ) {
		if ( 'post_title' === $field['type'] ) {
			return (string) $post->post_title;
		}
		return (string) get_post_meta( $post->ID, (string) $field['key'], true );
	}

	/**
	 * @param array<string,mixed>  $backup Backup record.
	 * @param array<string,string> $field Field definition.
	 */
	private static function backup_field_value( $backup, $field ) {
		if ( 'post_title' === $field['type'] ) {
			return isset( $backup['post_title'] ) ? (string) $backup['post_title'] : '';
		}
		$key = (string) $field['key'];
		return isset( $backup['meta'][ $key ] ) ? (string) $backup['meta'][ $key ] : '';
	}

	/**
	 * Write one guarded field. Title writes preserve the original post dates
	 * so this metadata repair does not masquerade as a fresh content edit.
	 *
	 * @param WP_Post              $post Post.
	 * @param array<string,string> $field Field definition.
	 * @param string               $value New value.
	 * @param bool                 $allow_empty True on rollback (delete empty meta).
	 */
	private static function write_field( $post, $field, $value, $allow_empty = false ) {
		if ( 'post_title' === $field['type'] ) {
			$result = wp_update_post(
				array(
					'ID'              => (int) $post->ID,
					'post_title'      => $value,
					'post_date'       => $post->post_date,
					'post_date_gmt'   => $post->post_date_gmt,
					'post_modified'   => $post->post_modified,
					'post_modified_gmt' => $post->post_modified_gmt,
					'edit_date'       => true,
				),
				true
			);
			return ! is_wp_error( $result ) && (int) $result === (int) $post->ID;
		}

		$key = (string) $field['key'];
		if ( '' === $value && $allow_empty ) {
			delete_post_meta( $post->ID, $key );
			return '' === (string) get_post_meta( $post->ID, $key, true );
		}
		update_post_meta( $post->ID, $key, $value );
		return $value === (string) get_post_meta( $post->ID, $key, true );
	}

	public static function handle_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sa-seo-meta-fix' ) );
		}
		check_admin_referer( 'sa_seo_meta_fix_action' );

		$action = isset( $_POST['sa_action'] ) ? sanitize_key( wp_unslash( $_POST['sa_action'] ) ) : '';
		if ( 'apply' === $action ) {
			self::apply_changes( 'manual' );
			$done = 'applied';
		} elseif ( 'rollback' === $action ) {
			self::rollback();
			$done = 'rolled_back';
		} else {
			$done = 'none';
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'         => self::PAGE_SLUG,
					'sa_seo_done'  => $done,
				),
				admin_url( 'tools.php' )
			)
		);
		exit;
	}

	public static function admin_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$report = get_option( self::REPORT_OPTION, array() );
		if ( ! is_array( $report ) || empty( $report['counts'] ) ) {
			return;
		}
		$c = $report['counts'];
		$url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );
		echo '<div class="notice notice-success is-dismissible"><p>';
		echo esc_html(
			sprintf(
				'اصلاح یک‌بارهٔ متادیتای سئو اجرا شده است: %1$d فیلد به‌روزرسانی، %2$d فیلد از قبل درست، %3$d فیلد ردشده به‌دلیل تغییر بعد از ممیزی. پس از اجرای ممیزی مقالات و اطمینان از نتیجه، این افزونه را غیرفعال و حذف کنید.',
				(int) $c['updated'],
				(int) $c['already_target'],
				(int) $c['skipped_changed']
			)
		);
		echo ' <a href="' . esc_url( $url ) . '">' . esc_html__( 'مشاهدهٔ گزارش', 'sa-seo-meta-fix' ) . '</a>';
		echo '</p></div>';
	}

	private static function status_label( $status ) {
		$labels = array(
			'updated'         => 'به‌روزرسانی شد',
			'already_target'  => 'از قبل مقدار هدف بود',
			'skipped_changed' => 'رد شد: مقدار فعلی با ممیزی فرق دارد',
			'failed'          => 'خطا در ذخیره/راستی‌آزمایی',
			'not_found'       => 'نوشته پیدا نشد',
			'restored'        => 'بازگردانده شد',
			'already_backup'  => 'از قبل مقدار پشتیبان بود',
		);
		return isset( $labels[ $status ] ) ? $labels[ $status ] : (string) $status;
	}

	/**
	 * @param array<string,mixed>|false $report Saved report.
	 */
	private static function render_report_table( $report ) {
		if ( ! is_array( $report ) || empty( $report['records'] ) ) {
			echo '<p>' . esc_html__( 'هنوز گزارشی ثبت نشده است.', 'sa-seo-meta-fix' ) . '</p>';
			return;
		}
		echo '<p>' . esc_html( sprintf( 'زمان اجرا: %s', isset( $report['time'] ) ? $report['time'] : '—' ) ) . '</p>';
		echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>';
		echo '<th>' . esc_html__( 'نامک', 'sa-seo-meta-fix' ) . '</th>';
		echo '<th>' . esc_html__( 'فیلد', 'sa-seo-meta-fix' ) . '</th>';
		echo '<th>' . esc_html__( 'وضعیت', 'sa-seo-meta-fix' ) . '</th>';
		echo '<th>' . esc_html__( 'قبل', 'sa-seo-meta-fix' ) . '</th>';
		echo '<th>' . esc_html__( 'هدف', 'sa-seo-meta-fix' ) . '</th>';
		echo '<th>' . esc_html__( 'بعد', 'sa-seo-meta-fix' ) . '</th>';
		echo '</tr></thead><tbody>';
		foreach ( $report['records'] as $record ) {
			if ( ! empty( $record['status'] ) && 'not_found' === $record['status'] ) {
				echo '<tr><td><code>' . esc_html( $record['slug'] ) . '</code></td><td colspan="5">' . esc_html( self::status_label( 'not_found' ) ) . '</td></tr>';
				continue;
			}
			foreach ( (array) $record['fields'] as $field ) {
				echo '<tr>';
				echo '<td><code>' . esc_html( $record['slug'] ) . '</code><br>#' . esc_html( (string) $record['post_id'] ) . '</td>';
				echo '<td>' . esc_html( $field['label'] ) . '</td>';
				echo '<td>' . esc_html( self::status_label( $field['status'] ) ) . '</td>';
				echo '<td>' . esc_html( $field['before'] ) . '</td>';
				echo '<td>' . esc_html( $field['target'] ) . '</td>';
				echo '<td>' . esc_html( $field['after'] ) . '</td>';
				echo '</tr>';
			}
		}
		echo '</tbody></table>';
	}

	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'دسترسی کافی ندارید.', 'sa-seo-meta-fix' ) );
		}
		$report   = get_option( self::REPORT_OPTION, array() );
		$rollback = get_option( self::ROLLBACK_OPTION, array() );
		$has_backup = (bool) get_option( self::BACKUP_OPTION, array() );

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'اصلاح یک‌بارهٔ متادیتای سئو (فاز ۳)', 'sa-seo-meta-fix' ) . '</h1>';
		echo '<p style="max-width:900px">' . esc_html__( 'این افزونه فقط عنوان، عنوان سئو و توضیح سئوی شش راهنمای مشخص را اصلاح می‌کند. تغییر فقط وقتی انجام می‌شود که مقدار فعلی دقیقاً همان مقدار مشاهده‌شده در ممیزی ۲۰۲۶-۱۱-۱۱ باشد؛ اگر از آن زمان خودتان مقداری را عوض کرده باشید، آن فیلد رد می‌شود و دست نمی‌خورد. متن مقاله، تصویر، FAQ، منابع و تاریخ نوشته تغییر نمی‌کنند.', 'sa-seo-meta-fix' ) . '</p>';

		if ( isset( $_GET['sa_seo_done'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display flag only.
			$done = sanitize_key( wp_unslash( $_GET['sa_seo_done'] ) );
			if ( 'applied' === $done ) {
				echo '<div class="notice notice-success"><p>' . esc_html__( 'اجرای اصلاحات انجام شد؛ گزارش تازه در ادامه آمده است.', 'sa-seo-meta-fix' ) . '</p></div>';
			} elseif ( 'rolled_back' === $done ) {
				echo '<div class="notice notice-warning"><p>' . esc_html__( 'بازگردانی نسخهٔ پشتیبان اجرا شد؛ گزارش آن در ادامه آمده است.', 'sa-seo-meta-fix' ) . '</p></div>';
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin:16px 0">';
		echo '<input type="hidden" name="action" value="sa_seo_meta_fix">';
		wp_nonce_field( 'sa_seo_meta_fix_action' );
		echo '<button class="button button-primary" type="submit" name="sa_action" value="apply">' . esc_html__( 'اجرای دوبارهٔ اصلاحات', 'sa-seo-meta-fix' ) . '</button> ';
		if ( $has_backup ) {
			echo '<button class="button" type="submit" name="sa_action" value="rollback" onclick="return confirm(\'' . esc_js( __( 'مقادیر پشتیبانِ پیش از اصلاح برگردانده شوند؟', 'sa-seo-meta-fix' ) ) . '\')">' . esc_html__( 'بازگردانی نسخهٔ پشتیبان', 'sa-seo-meta-fix' ) . '</button>';
		}
		echo '</form>';

		echo '<h2>' . esc_html__( 'گزارش آخرین اصلاح', 'sa-seo-meta-fix' ) . '</h2>';
		self::render_report_table( is_array( $report ) ? $report : false );

		if ( is_array( $rollback ) && ! empty( $rollback['records'] ) ) {
			echo '<h2>' . esc_html__( 'گزارش آخرین بازگردانی', 'sa-seo-meta-fix' ) . '</h2>';
			self::render_report_table( $rollback );
		}

		echo '<h2>' . esc_html__( 'پس از اطمینان', 'sa-seo-meta-fix' ) . '</h2>';
		echo '<ol style="max-width:900px">';
		echo '<li>' . esc_html__( 'افزونهٔ «ممیزی مقالات زنده» را اجرا و دانلود کنید تا صفرشدن اختلاف‌های sa_seo_title و sa_seo_description تأیید شود.', 'sa-seo-meta-fix' ) . '</li>';
		echo '<li>' . esc_html__( 'اگر نتیجه درست بود، همین افزونهٔ یک‌بارمصرف را از بخش افزونه‌ها غیرفعال و حذف کنید. حذف افزونه، گزارش و نسخهٔ پشتیبان موقتش را هم پاک می‌کند.', 'sa-seo-meta-fix' ) . '</li>';
		echo '<li>' . esc_html__( 'اگر نتیجه نادرست بود، پیش از حذف افزونه از دکمهٔ «بازگردانی نسخهٔ پشتیبان» در همین صفحه استفاده کنید.', 'sa-seo-meta-fix' ) . '</li>';
		echo '</ol>';
		echo '</div>';
	}
}

SA_SEO_Meta_Fix::boot();

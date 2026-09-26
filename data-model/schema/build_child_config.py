#!/usr/bin/env python3
"""Generate wp-content/themes/sarzaminaryan-child/inc/entities-config.php from data-model.yaml.

The child theme never hard-codes entity fields: this script is the single bridge between
MASTER_DATA_MODEL (Level 1–5) and WordPress (CPTs, meta boxes, publish gate, schema).
Run after every model change:  python3 data-model/schema/build_child_config.py
"""
import os
import sys

import yaml

HERE = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.abspath(os.path.join(HERE, '..', '..'))
SRC = os.path.join(HERE, 'data-model.yaml')
OUT = os.path.join(ROOT, 'wp-content', 'themes', 'sarzaminaryan-child', 'inc', 'entities-config.php')

# ---- Persian UI labels ------------------------------------------------------
ENTITY_LABELS = {
    'province':      dict(singular='استان', plural='استان‌ها', icon='dashicons-location-alt', menu_pos=1),
    'city':          dict(singular='شهر', plural='شهرها', icon='dashicons-building', menu_pos=2),
    'attraction':    dict(singular='جاذبه', plural='جاذبه‌ها', icon='dashicons-camera-alt', menu_pos=3),
    'travel_route':  dict(singular='مسیر سفر', plural='مسیرهای سفر', icon='dashicons-randomize', menu_pos=4),
    'local_food':    dict(singular='غذای محلی', plural='غذاهای محلی', icon='dashicons-food', menu_pos=5),
    'souvenir':      dict(singular='سوغات', plural='سوغات', icon='dashicons-cart', menu_pos=6),
    'accommodation': dict(singular='اقامتگاه', plural='اقامتگاه‌ها', icon='dashicons-admin-multisite', menu_pos=7),
}
# URL base per Level 4 (pattern "/base/{slug}")
FIELD_LABELS = {
    'province_center_city': 'مرکز استان', 'province_population': 'جمعیت', 'province_area': 'مساحت (کیلومتر مربع)',
    'province_latitude': 'عرض جغرافیایی', 'province_longitude': 'طول جغرافیایی', 'province_climate': 'اقلیم',
    'city_population': 'جمعیت', 'city_elevation': 'ارتفاع از سطح دریا (متر)', 'city_latitude': 'عرض جغرافیایی',
    'city_longitude': 'طول جغرافیایی', 'access_air': 'دسترسی هوایی', 'access_rail': 'دسترسی ریلی',
    'access_road': 'دسترسی جاده‌ای', 'google_map_url': 'لینک گوگل‌مپ',
    'latitude': 'عرض جغرافیایی', 'longitude': 'طول جغرافیایی', 'address': 'نشانی', 'opening_hours': 'ساعات بازدید',
    'ticket_price': 'قیمت بلیت', 'visit_duration': 'مدت بازدید پیشنهادی',
    'route_distance': 'مسافت مسیر (کیلومتر)',
    'main_ingredients': 'مواد اصلی (هر مورد در یک خط)', 'serving_method': 'نحوه سرو',
    'purchase_location': 'محل خرید',
    'star_rating': 'ستاره (۱ تا ۵)', 'price_per_night_min': 'حداقل قیمت هر شب', 'price_per_night_max': 'حداکثر قیمت هر شب',
    'phone': 'تلفن', 'website_url': 'وب‌سایت', 'booking_url': 'لینک رزرو', 'affiliate_provider': 'ارائه‌دهنده همکاری',
    'is_sponsored': 'محتوای اسپانسری', 'amenities': 'امکانات (هر مورد در یک خط)', 'check_in_time': 'ساعت ورود',
    'check_out_time': 'ساعت خروج', 'capacity': 'ظرفیت',
}
TAX_LABELS = {
    'province_tax':       dict(singular='استان', plural='استان‌ها', slug='ostan', hierarchical=True),
    'attraction_type':    dict(singular='نوع جاذبه', plural='انواع جاذبه', slug='attraction-type', hierarchical=True),
    'travel_season':      dict(singular='فصل سفر', plural='فصل‌های سفر', slug='season', hierarchical=False),
    'travel_budget':      dict(singular='بودجه سفر', plural='بودجه‌های سفر', slug='budget', hierarchical=False),
    'travel_duration':    dict(singular='مدت سفر', plural='مدت‌های سفر', slug='duration', hierarchical=False),
    'accommodation_type': dict(singular='نوع اقامتگاه', plural='انواع اقامتگاه', slug='accommodation-type', hierarchical=True),
}
TERM_LABELS = {
    'attraction_type': dict(historical='تاریخی', cultural='فرهنگی', religious='مذهبی', nature='طبیعی', mountain='کوهستانی',
                            forest='جنگلی', desert='کویری', beach='ساحلی', island='جزیره‌ای', village='روستایی',
                            ecotourism='بوم‌گردی', adventure='ماجراجویی'),
    'travel_season': dict(spring='بهار', summer='تابستان', autumn='پاییز', winter='زمستان'),
    'travel_budget': dict(economic='اقتصادی', medium='متوسط', luxury='لوکس'),
    'travel_duration': {'one_day': 'یک‌روزه', 'weekend': 'آخر هفته', '3_to_5_days': '۳ تا ۵ روز', 'more_than_5_days': 'بیش از ۵ روز'},
    'accommodation_type': dict(hotel='هتل', eco_lodge='اقامتگاه بوم‌گردی', guest_house='مهمان‌پذیر',
                               traditional_house='خانه سنتی', camping='کمپینگ'),
}
SKIP_SUFFIX = ('_name', '_slug', '_description', '_summary')
UI_TYPE = {'string': 'text', 'text': 'textarea', 'integer': 'integer', 'number': 'number', 'float': 'float',
           'url': 'url', 'list': 'list', 'reference': 'reference', 'boolean': 'boolean', 'time': 'time'}


def php(v, indent=0):
    """Serialize python data to PHP array literal (short syntax)."""
    pad = '\t' * indent
    if isinstance(v, dict):
        if not v:
            return 'array()'
        inner = ',\n'.join(f"{pad}\t{php(k)} => {php(val, indent + 1)}" for k, val in v.items())
        return f"array(\n{inner},\n{pad})"
    if isinstance(v, (list, tuple)):
        if not v:
            return 'array()'
        inner = ',\n'.join(f"{pad}\t{php(x, indent + 1)}" for x in v)
        return f"array(\n{inner},\n{pad})"
    if isinstance(v, bool):
        return 'true' if v else 'false'
    if v is None:
        return 'null'
    if isinstance(v, (int, float)):
        return str(v)
    return "'" + str(v).replace('\\', '\\\\').replace("'", "\\'") + "'"


def main():
    with open(SRC, encoding='utf-8') as fh:
        model = yaml.safe_load(fh)

    entities = {}
    for key, e in model['entities'].items():
        lab = ENTITY_LABELS[key]
        base = e['url_pattern'].split('/')[1]
        fields = []
        for f in e['fields']:
            name = f['name']
            t = f['type']
            if name.endswith(SKIP_SUFFIX) or t in ('image', 'slug', 'enum'):
                continue
            field = dict(name=name, label=FIELD_LABELS.get(name, name), type=UI_TYPE[t], key='sa_' + name)
            if t == 'reference':
                field['key'] = 'sa_' + name + '_id'
                field['target'] = f['target']
            if 'unit' in f:
                field['unit'] = f['unit']
            if 'min' in f:
                field['min'] = f['min']
            if 'max' in f:
                field['max'] = f['max']
            fields.append(field)
        relations = []
        for r in e['relations']:
            rel = dict(type=r['type'], target=r['target'], status=r['status'])
            if r['type'] == 'belongs_to':
                rel['key'] = 'sa_' + r['target'] + '_id'
            elif r['type'] == 'belongs_to_many':
                rel['key'] = 'sa_' + r['target'] + '_ids'
            elif r['type'] == 'related_many':
                rel['key'] = 'sa_related_' + r['target'] + '_ids'
            elif r['type'] == 'near_many':
                rel['key'] = 'sa_near_' + r['target'] + '_ids'
            if r.get('required'):
                rel['required'] = True
            relations.append(rel)
        # summary field → excerpt; description field → content
        summary = next((f['name'] for f in e['fields'] if f['name'].endswith('_summary')), None)
        description = next((f['name'] for f in e['fields'] if f['name'].endswith('_description')), None)
        enums = [dict(field=f['name'], taxonomy=f['taxonomy']) for f in e['fields'] if f['type'] == 'enum']
        entities[key] = dict(
            cpt=e['cpt'], status=e['status'], label=e['label'], singular=lab['singular'], plural=lab['plural'],
            icon=lab['icon'], menu_pos=lab['menu_pos'], url_base=base, primary_key=e['primary_key'],
            primary_taxonomy=e['primary_taxonomy'], summary_field=summary, description_field=description,
            fields=fields, relations=relations, enums=enums,
            taxonomies=sorted({e['primary_taxonomy']} | {x['taxonomy'] for x in enums}),
        )

    taxonomies = {}
    for key, t in model['taxonomies'].items():
        lab = TAX_LABELS[key]
        terms = t['terms']
        term_list = [] if isinstance(terms, str) else [dict(slug=s, name=TERM_LABELS[key][s]) for s in terms]
        taxonomies[key] = dict(status=t['status'], singular=lab['singular'], plural=lab['plural'], slug=lab['slug'],
                               hierarchical=lab['hierarchical'], applies_to=t['applies_to'], terms=term_list,
                               source=terms if isinstance(terms, str) else None)

    seo_required = model['seo_fields']['required']
    blockers = model['content_rules']['publish_blockers']
    out = f"""<?php
/**
 * GENERATED FILE — do not edit by hand.
 * Source: data-model/schema/data-model.yaml (model v{model['model']['version']}) via build_child_config.py
 * Maps MASTER_DATA_MODEL Levels 1–5 onto WordPress: CPTs, meta keys, relations, taxonomies, SEO fields.
 *
 * @package Sarzaminaryan_Child
 */

if ( ! defined( 'ABSPATH' ) ) {{
	exit;
}}

/**
 * Entity definitions keyed by CPT slug.
 */
function sa_entities_config() {{
	static $config = null;
	if ( null !== $config ) {{
		return $config;
	}}
	$config = {php(entities, 1)};
	return $config;
}}

/**
 * Taxonomy definitions keyed by taxonomy slug.
 */
function sa_taxonomies_config() {{
	return {php(taxonomies, 1)};
}}

/**
 * Level 5 — SEO fields required on every entity (meta keys are prefixed with sa_).
 */
function sa_seo_required_fields() {{
	return {php(seo_required, 1)};
}}

/**
 * Level 7 — publish blockers.
 */
function sa_publish_blockers() {{
	return {php(blockers, 1)};
}}
"""
    os.makedirs(os.path.dirname(OUT), exist_ok=True)
    with open(OUT, 'w', encoding='utf-8') as fh:
        fh.write(out)
    print('wrote', os.path.relpath(OUT, ROOT), '| entities:', len(entities), '| taxonomies:', len(taxonomies))


if __name__ == '__main__':
    sys.exit(main())

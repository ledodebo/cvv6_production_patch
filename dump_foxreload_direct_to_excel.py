#!/usr/bin/env python3
"""
Direct FoxReload API to Excel Catalog Dumper
Crawl the live FoxReload API directly and exports the entire digital goods catalog
straight into a structured, production-ready Excel spreadsheet (.xlsx).

Features:
- Zero intermediate JSON files.
- High-speed concurrent fetching via ThreadPoolExecutor.
- Automatic rate-limit handling (HTTP 429) with exponential backoff.
- Maps supplier roots into canonical 6valley categories:
  'Gaming Cards', 'Gift Cards', 'Subscriptions', 'Digital Services', 'Telecom & eSIM'.
- Auto-detects multi-country brands (Is Master Product = Yes) vs platform editions (Is Master Product = No).
- Generates 100% compatible Excel format for 6valley Dashboard Bulk Import.
"""

import sys
import os
import re
import time
import argparse
import hashlib
from concurrent.futures import ThreadPoolExecutor, as_completed
import requests
import openpyxl
from openpyxl.styles import Font, PatternFill, Alignment, Border, Side
from openpyxl.utils import get_column_letter
from openpyxl.worksheet.datavalidation import DataValidation

# Ensure UTF-8 output
try:
    sys.stdout.reconfigure(encoding='utf-8', line_buffering=True)
except AttributeError:
    pass

# ==============================================================================
# 1. CONFIGURATION
# ==============================================================================
def load_env():
    env_paths = ['.env', '../.env', os.path.join(os.path.dirname(__file__), '.env')]
    config = {
        'FOXRELOAD_API_KEY': 'dmk_NzUT8k3M-sTu5Vg5dEyjiyEMED2qhOoA-ngnrBGQDiY',
        'FOXRELOAD_BASE_URL': 'https://public-api.foxreload.com/api',
        'FOXRELOAD_MARKUP_PERCENT': 7.0,
        'FOXRELOAD_CURRENCY': 'usd',
        'FOXRELOAD_LANGUAGE': 'en',
    }
    for p in env_paths:
        if os.path.exists(p):
            with open(p, 'r', encoding='utf-8', errors='ignore') as f:
                for line in f:
                    line = line.strip()
                    if not line or line.startswith('#') or '=' not in line:
                        continue
                    k, v = line.split('=', 1)
                    k, v = k.strip(), v.strip().strip('"').strip("'")
                    if k in config:
                        if k == 'FOXRELOAD_MARKUP_PERCENT':
                            try:
                                config[k] = float(v)
                            except ValueError:
                                pass
                        else:
                            config[k] = v
            break
    return config

ENV = load_env()

HEADERS = {
    'X-API-Key': ENV['FOXRELOAD_API_KEY'],
    'X-Currency': ENV['FOXRELOAD_CURRENCY'].lower(),
    'X-Language': ENV['FOXRELOAD_LANGUAGE'].lower(),
    'Accept': 'application/json',
    'User-Agent': 'FoxReload-Direct-Excel-Sync/3.0'
}

# ==============================================================================
# 2. CANONICAL CATEGORY MAPPINGS
# ==============================================================================
ROOT_TO_CANONICAL = {
    'esim': 'Telecom & eSIM',
    'gift cards': 'Gift Cards',
    'app stores': 'Gift Cards',
    'rewarble': 'Gift Cards',
    'financial & prepaid products': 'Gift Cards',
    'financial & prepaid': 'Gift Cards',
    'subscriptions & digital services': 'Subscriptions',
    'subscriptions': 'Subscriptions',
    'in-game top-ups': 'Gaming Cards',
    'game currency (codes)': 'Gaming Cards',
    'game currency': 'Gaming Cards',
    'games': 'Gaming Cards',
    'gaming services': 'Gaming Cards',
}

# Specific overrides for social/live-streaming apps or telecom
BRAND_OVERRIDES = {
    # Telecom & eSIM
    'esim': 'Telecom & eSIM',
    'esimchoice': 'Telecom & eSIM',
    'etisalat': 'Telecom & eSIM',
    'du': 'Telecom & eSIM',
    'lebara': 'Telecom & eSIM',
    'lycamobile': 'Telecom & eSIM',
    'pcs recharge': 'Telecom & eSIM',
    'vodafone': 'Telecom & eSIM',
    'orange': 'Telecom & eSIM',
    'stc': 'Telecom & eSIM',
    'zain': 'Telecom & eSIM',
    'mobily': 'Telecom & eSIM',

    # Digital Services (Live streaming / virtual gifts)
    'bigo live': 'Digital Services',
    'soulchill': 'Digital Services',
    'hello yo': 'Digital Services',
    'tango live': 'Digital Services',
    'likee': 'Digital Services',
    'poppo live': 'Digital Services',
    'chamet': 'Digital Services',
    'livu': 'Digital Services',
    'mico': 'Digital Services',
    'tiktok': 'Digital Services',
    'tiktok coins': 'Digital Services',
    'weyyak': 'Subscriptions',
    'podu': 'Subscriptions',
    'tinder': 'Subscriptions',
    'netflix': 'Subscriptions',
    'spotify': 'Subscriptions',
    'disney': 'Subscriptions',
    'shahid': 'Subscriptions',
    'starzplay': 'Subscriptions',
    'anghami': 'Subscriptions',
    'osn': 'Subscriptions',
    'crunchyroll': 'Subscriptions',
}

def resolve_canonical_category(root_name, brand_name):
    b_clean = (brand_name or '').lower().strip()
    for k, cat in BRAND_OVERRIDES.items():
        if k in b_clean:
            return cat

    r_clean = (root_name or '').lower().strip()
    return ROOT_TO_CANONICAL.get(r_clean, 'Gaming Cards')

# ==============================================================================
# 3. COUNTRY & PLATFORM RECOGNITION
# ==============================================================================
COUNTRY_MAP = {
    'united kingdom': ('United Kingdom', 'GB'),
    'uk': ('United Kingdom', 'GB'),
    'great britain': ('United Kingdom', 'GB'),
    'usa': ('USA', 'US'),
    'united states': ('USA', 'US'),
    'us': ('USA', 'US'),
    'uae': ('UAE', 'AE'),
    'united arab emirates': ('UAE', 'AE'),
    'saudi arabia': ('Saudi Arabia', 'SA'),
    'ksa': ('Saudi Arabia', 'SA'),
    'saudi': ('Saudi Arabia', 'SA'),
    'canada': ('Canada', 'CA'),
    'egypt': ('Egypt', 'EG'),
    'germany': ('Germany', 'DE'),
    'kuwait': ('Kuwait', 'KW'),
    'netherlands': ('Netherlands', 'NL'),
    'qatar': ('Qatar', 'QA'),
    'morocco': ('Morocco', 'MA'),
    'france': ('France', 'FR'),
    'spain': ('Spain', 'ES'),
    'italy': ('Italy', 'IT'),
    'turkey': ('Turkey', 'TR'),
    'türkiye': ('Turkey', 'TR'),
    'brazil': ('Brazil', 'BR'),
    'india': ('India', 'IN'),
    'australia': ('Australia', 'AU'),
    'austria': ('Austria', 'AT'),
    'belgium': ('Belgium', 'BE'),
    'denmark': ('Denmark', 'DK'),
    'finland': ('Finland', 'FI'),
    'ireland': ('Ireland', 'IE'),
    'japan': ('Japan', 'JP'),
    'poland': ('Poland', 'PL'),
    'sweden': ('Sweden', 'SE'),
    'switzerland': ('Switzerland', 'CH'),
    'south korea': ('South Korea', 'KR'),
    'korea': ('South Korea', 'KR'),
    'jordan': ('Jordan', 'JO'),
    'bahrain': ('Bahrain', 'BH'),
    'oman': ('Oman', 'OM'),
    'algeria': ('Algeria', 'DZ'),
    'tunisia': ('Tunisia', 'TN'),
    'iraq': ('Iraq', 'IQ'),
    'lebanon': ('Lebanon', 'LB'),
    'singapore': ('Singapore', 'SG'),
    'malaysia': ('Malaysia', 'MY'),
    'norway': ('Norway', 'NO'),
    'portugal': ('Portugal', 'PT'),
    'slovakia': ('Slovakia', 'SK'),
    'czech republic': ('Czech Republic', 'CZ'),
    'hungary': ('Hungary', 'HU'),
    'greece': ('Greece', 'GR'),
    'europe': ('Europe', 'EU'),
    'eu': ('Europe', 'EU'),
    'global': ('Global', 'GL'),
    'worldwide': ('Global', 'GL'),
    'world': ('Global', 'GL'),
    'ww': ('Global', 'GL'),

    # Asia & Southeast Asia
    'philippines': ('Philippines', 'PH'),
    'indonesia': ('Indonesia', 'ID'),
    'thailand': ('Thailand', 'TH'),
    'vietnam': ('Vietnam', 'VN'),
    'cambodia': ('Cambodia', 'KH'),
    'taiwan': ('Taiwan', 'TW'),
    'macau': ('Macau', 'MO'),
    'hong kong': ('Hong Kong', 'HK'),
    'hong kong (sar)': ('Hong Kong', 'HK'),
    'china': ('China', 'CN'),
    'my/sg': ('Malaysia / Singapore', 'MY'),
    'hk/mo/tw': ('HK / MO / TW', 'TW'),
    'southeast asia': ('Southeast Asia', 'SEA'),
    'sea': ('Southeast Asia', 'SEA'),
    'asia': ('Asia', 'AS'),

    # Middle East & North Africa
    'middle east and north africa': ('Middle East and North Africa', 'ME'),
    'mena': ('Middle East and North Africa', 'ME'),

    # Americas & Oceania
    'latin america': ('Latin America', 'LA'),
    'latam': ('Latin America', 'LA'),
    'north america': ('North America', 'NA'),
    'america': ('America', 'AM'),
    'new zealand': ('New Zealand', 'NZ'),
    'nz': ('New Zealand', 'NZ'),
    'chile': ('Chile', 'CL'),
    'colombia': ('Colombia', 'CO'),
    'peru': ('Peru', 'PE'),
    'mexico': ('Mexico', 'MX'),
    'ukraine': ('Ukraine', 'UA'),
    'russia': ('Russia', 'RU'),
}

# ==============================================================================
# 3B. CURATED DOMAIN MAPPINGS (REGIONAL MASTERS VS DISTINCT PRODUCTS)
# ==============================================================================
CONSOLIDATED_REGIONAL_BRANDS = {
    'bilibili': 'Bilibili',
    'ace racer': 'Ace Racer',
    'wild rift': 'Wild Rift',
    'valorant': 'Valorant',
    'ea sports fc™ mobile': 'EA SPORTS FC™ Mobile',
    'call of duty mobile (garena)': 'Call of Duty Mobile (Garena)',
    'delta force (garena)': 'Delta Force (Garena)',
    'arena of valor': 'Arena of Valor',
    'blood strike': 'Blood Strike',
    'love and deepspace': 'Love and Deepspace',
    'racing master': 'Racing Master',
    'punishing: gray raven': 'Punishing: Gray Raven',
    'dragon nest m classic': 'Dragon Nest M Classic',
    'sword of justice': 'Sword of Justice',
    'undawn (garena)': 'Undawn (Garena)',
    'ardene': 'Ardene',
    'watsons': 'Watsons',
    'the home depot': 'The Home Depot',
    'calvin klein': 'Calvin Klein',
    'hush puppies': 'Hush Puppies',
    'lakeland': 'Lakeland',
    'inglot': 'Inglot',
    'naturalizer': 'Naturalizer',
    'reel cinemas': 'Reel Cinemas',
    'elearngift': 'eLearnGift',
}

# Brands with independent edition/platform/currency items that must remain separate
DISTINCT_EDITION_BRANDS = [
    'payment cards for services',
    'overwatch 2',
    'pubg',
    'yalla ludo',
    'binance',
    'jawaker',
    'delta force',
]

PLATFORM_KEYWORDS = [
    'steam pc', 'steam', 'mobile uc', 'uc', 'new state', 'pc key', 'pc / ea app', 'ea app',
    'xbox', 'playstation', 'psn', 'nitro', 'basic', 'diamonds', 'gold', 'coins', 'points',
    'pass', 'plus', 'deluxe', 'standard edition', 'ultimate edition', 'g-coin'
]

def is_country_label(label):
    if not label:
        return False, None, None
    clean = label.lower().strip(" -:_|()\t\n\r")
    for kw in PLATFORM_KEYWORDS:
        if kw in clean:
            return False, None, None
    for alias, (c_name, c_code) in COUNTRY_MAP.items():
        if re.search(r'\b' + re.escape(alias) + r'\b', clean):
            return True, c_name, c_code
    return False, None, None

def detect_item_country(item_name, fallback_country=None):
    if not item_name:
        return fallback_country, 'GL' if fallback_country else None
    match = re.search(r'\(([^)]+)\)', item_name)
    if match:
        extracted = match.group(1).strip()
        is_c, c_name, c_code = is_country_label(extracted)
        if is_c and c_name:
            return c_name, c_code
    is_c, c_name, c_code = is_country_label(item_name)
    if is_c and c_name:
        return c_name, c_code
    return fallback_country, 'GL' if fallback_country else None

def clean_group_label(brand_name, sub_name):
    clean = re.sub(re.escape(brand_name), '', sub_name, flags=re.IGNORECASE)
    clean = re.sub(r'\b(Apex|EA SPORTS FC|EA Sports FC|FIFA|Fortnite|Minecraft|PUBG|PUBG Mobile|Netflix|Amazon|Yalla Ludo|Jawaker|Apple|Google Play)\b', '', clean, flags=re.IGNORECASE)
    clean = clean.strip(" -:_|()\t\n\r\0\x0B")
    clean_lower = clean.lower()
    if 'steam' in clean_lower or 'g-coin' in clean_lower:
        return 'Steam PC'
    if 'uc' in clean_lower:
        return 'Mobile UC'
    if 'nitro' in clean_lower:
        return 'Nitro'
    if 'basic' in clean_lower:
        return 'Basic'
    if 'xbox' in clean_lower:
        return 'Xbox'
    if any(k in clean_lower for k in ['playstation', 'psn']):
        return 'PlayStation'
    if any(k in clean_lower for k in ['ea pc', 'pc points']) or re.search(r'\b(PC Key|PC)\b', clean, re.I):
        return 'PC / EA App'
    for alias, (c_name, _) in COUNTRY_MAP.items():
        if alias in ['global', 'worldwide']:
            continue
        if re.search(r'\b' + re.escape(alias) + r'\b', clean_lower):
            return c_name
    return clean if clean else sub_name

def extract_label(item_name, brand_name, region_name):
    clean = item_name
    for remove in [brand_name, region_name, 'key', 'code', 'global', 'redeem', 'instant', 'delivery', '()', '—', '-']:
        if remove:
            clean = re.sub(re.escape(remove), '', clean, flags=re.IGNORECASE)
    clean = re.sub(r'\s*\([^)]*\)\s*$', '', clean)
    clean = re.sub(r'\s+', ' ', clean).strip(" -:_|()\t\n\r")
    return clean if clean else item_name

def split_service_item(brand_name, item_name):
    """
    Splits a single-region / global service item into:
    (clean_product_name, variation_label)
    according to Law 2 (Single-Region Distinct Plan Separation).
    """
    raw = item_name
    has_vat = bool(re.search(r'\(incl\.?\s*27%\s*vat\)', raw, re.IGNORECASE))
    clean_no_vat = re.sub(r'\(incl\.?\s*27%\s*vat\)', '', raw, flags=re.IGNORECASE).strip()
    clean_name = re.sub(re.escape(brand_name), '', clean_no_vat, flags=re.IGNORECASE).strip(" -:_()")
    is_renewal = bool(re.search(r'\brenewal\b', raw, re.IGNORECASE))
    clean_no_renewal = re.sub(r'\brenewal\b', '', clean_name, flags=re.IGNORECASE).strip(" -:_()")
    
    tiers = [
        ('Nitro Basic', r'\bnitro basic\b'),
        ('Nitro', r'\bnitro\b'),
        ('Server Boost', r'\bserver boost\b'),
        ('Premier', r'\bpremier\b'),
        ('Plus', r'\bplus\b'),
        ('Pro Plan', r'\bpro plan\b'),
        ('Basic Plan', r'\bbasic plan\b'),
        ('Standard Plan', r'\bstandard plan\b'),
        ('Mega Plan', r'\bmega plan\b'),
        ('Max 20x', r'\bmax 20x\b'),
        ('Pro', r'\bpro\b'),
        ('Ultra', r'\bultra\b'),
        ('Super', r'\bsuper\b'),
        ('Business', r'\bbusiness\b'),
        ('Creator', r'\bcreator\b'),
        ('Starter', r'\bstarter\b'),
        ('Basic', r'\bbasic\b'),
        ('Lite', r'\blite\b'),
        ('Beginner', r'\bbeginner\b'),
        ('Master', r'\bmaster\b'),
        ('Ultimate', r'\bultimate\b'),
        ('Premium+', r'\bpremium\+\b'),
        ('Premium', r'\bpremium\b'),
    ]
    
    matched_tier = None
    for tier_name, pattern in tiers:
        if re.search(pattern, clean_no_renewal, re.IGNORECASE):
            matched_tier = tier_name
            break
            
    if matched_tier:
        plan_label = f"Renewal {matched_tier}" if is_renewal else matched_tier
        prod_title = f"{brand_name} - {plan_label}"
    else:
        prod_title = brand_name
        
    dur_match = re.search(r'(\d+\s*(?:month|year|day|credit)s?)', clean_no_vat, re.IGNORECASE)
    duration = dur_match.group(1).title() if dur_match else ""
    
    if has_vat:
        var_label = f"{duration} (With 27% VAT)".strip() if duration else "With 27% VAT"
    elif duration:
        var_label = duration
    else:
        var_label = "Standard"
        
    return prod_title, var_label

# ==============================================================================
# 4. API CLIENT WITH RETRIES & CURSOR PAGINATION
# ==============================================================================
session = requests.Session()
session.headers.update(HEADERS)

def api_get(endpoint, params=None, retries=5):
    url = f"{ENV['FOXRELOAD_BASE_URL']}/{endpoint.lstrip('/')}"
    backoff = 1.0
    for attempt in range(1, retries + 1):
        try:
            r = session.get(url, params=params, timeout=30)
            if r.status_code == 200:
                return r.json()
            elif r.status_code == 429:
                time.sleep(backoff)
                backoff *= 1.8
            elif r.status_code in [500, 502, 503, 504]:
                time.sleep(backoff)
                backoff *= 1.5
            else:
                return {}
        except Exception as e:
            time.sleep(backoff)
            backoff *= 1.5
    return {}

def fetch_all_categories(parent_id=None, limit=100, max_items=None, in_stock_only=False):
    """Fetch categories under parentId using cursor pagination with optional early stopping."""
    items = []
    cursor = None
    while True:
        params = {'limit': limit}
        if parent_id:
            params['parentId'] = parent_id
        if in_stock_only:
            params['withStockOnly'] = 'true'
        if cursor:
            params['cursor'] = cursor

        data = api_get('/categories/', params)
        batch = data.get('items', [])
        if not batch:
            break

        for item in batch:
            if in_stock_only and item.get('inStockCount', 0) == 0:
                continue
            items.append(item)
            if max_items and len(items) >= max_items:
                return items

        cursor = data.get('nextCursor')
        if not cursor:
            break
    return items

def fetch_all_products(category_id, limit=100, with_stock_only=True):
    """Fetch all products for a category using offset/cursor pagination."""
    items = []
    cursor = None
    offset = 0
    while True:
        params = {'categoryId': category_id, 'limit': limit, 'offset': offset}
        if with_stock_only:
            params['withStockOnly'] = 'true'
        if cursor:
            params['cursor'] = cursor

        data = api_get('/products/', params)
        batch = data.get('items', [])
        if not batch:
            break
        items.extend(batch)
        cursor = data.get('nextCursor')
        offset += len(batch)

        total = data.get('total')
        if total is not None and offset >= total:
            break
        if not cursor and (total is None or offset >= total):
            break
    return items

# ==============================================================================
# 5. CONCURRENT BRAND PROCESSOR
# ==============================================================================
def process_single_brand(brand_item, root_name, markup, min_cost, in_stock_only):
    b_id = brand_item['id']
    b_name = brand_item['name']
    canonical_cat = resolve_canonical_category(root_name, b_name)

    # 1. Check for child subcategories under this brand
    subcategories = fetch_all_categories(parent_id=b_id, limit=100)
    
    brand_groups = []
    if subcategories:
        for sub in subcategories:
            if in_stock_only and sub.get('inStockCount', 0) == 0:
                continue
            prods = fetch_all_products(sub['id'], limit=100, with_stock_only=in_stock_only)
            if prods:
                brand_groups.append({
                    'category_id': sub['id'],
                    'category_name': sub.get('name') or 'Standard',
                    'items': prods
                })
    else:
        # Direct products under the brand category itself
        direct_prods = fetch_all_products(b_id, limit=100, with_stock_only=in_stock_only)
        if direct_prods:
            brand_groups.append({
                'category_id': b_id,
                'category_name': 'Standard',
                'items': direct_prods
            })

    if not brand_groups:
        return []

    # 2. Determine Master Product consolidation vs Distinct Products
    b_name_lower = b_name.lower().strip()
    if b_name_lower in CONSOLIDATED_REGIONAL_BRANDS:
        should_consolidate = True
        master_clean_name = CONSOLIDATED_REGIONAL_BRANDS[b_name_lower]
    elif any(d in b_name_lower for d in DISTINCT_EDITION_BRANDS):
        should_consolidate = False
        master_clean_name = b_name
    else:
        country_subcategories = 0
        detected_countries = set()
        for g in brand_groups:
            c_label = clean_group_label(b_name, g['category_name'])
            is_c, c_n, _ = is_country_label(c_label)
            if is_c:
                country_subcategories += 1
                detected_countries.add(c_n)
            for it in g.get('items', [])[:20]:
                is_c, c_n, _ = is_country_label(it.get('name', ''))
                if is_c and c_n:
                    detected_countries.add(c_n)

        should_consolidate = (country_subcategories > 1 or len(detected_countries) > 1)
        master_clean_name = b_name

    variation_rows = []
    if should_consolidate:
        # Master Product: 1 product row with country variations
        product_name = master_clean_name
        for group in brand_groups:
            raw_region = group.get('category_name') or 'Global'
            group_region = clean_group_label(b_name, raw_region)
            if not group_region or group_region.lower() in ['standard', 'default', 'package', b_name_lower]:
                group_region = 'Global'

            for item in group.get('items', []):
                raw_price = item.get('price')
                if raw_price is None or float(raw_price) <= 0:
                    continue
                cost_usd = float(raw_price)
                if cost_usd < min_cost:
                    continue

                retail_price = round(cost_usd * (1 + (markup / 100)), 2)
                detected_country, detected_code = detect_item_country(item.get('name', ''), fallback_country=group_region)
                country_name = detected_country or group_region
                country_code = detected_code or 'GL'
                if country_name.lower() in COUNTRY_MAP:
                    country_name, country_code = COUNTRY_MAP[country_name.lower()]

                label = extract_label(item['name'], b_name, country_name)
                sku = f"FR-{hashlib.md5(item['id'].encode()).hexdigest()[:8].upper()}"

                variation_rows.append({
                    'category': canonical_cat,
                    'brand': b_name,
                    'product_name': product_name,
                    'is_master_product': 'Yes',
                    'country_name': country_name,
                    'country_code': country_code,
                    'variation_name': label,
                    'cost_price': cost_usd,
                    'retail_price': retail_price,
                    'stock': int(item.get('quantity') or 999),
                    'sku': sku,
                    'provider_slug': 'foxreload',
                    'external_item_id': item['id'],
                    'external_category_id': group.get('category_id') or '',
                    'status': 1,
                    'thumbnail': 'product.png'
                })
    else:
        # Distinct edition products (e.g. PUBG Mobile vs Steam PC, ChatGPT, Midjourney, etc.)
        for group in brand_groups:
            g_name = group.get('category_name') or ''
            g_label = clean_group_label(b_name, g_name)

            if b_name_lower == 'payment cards for services':
                # Each AI subcategory is its own independent service brand
                actual_name = g_name.strip()
                item_brand = actual_name
                item_cat = 'Subscriptions'
                base_prod_title = actual_name
            else:
                base_prod_title = f"{b_name} ({g_label})" if g_label and g_label.lower() not in ['standard', 'default', 'package', 'global'] else b_name
                item_brand = b_name
                item_cat = canonical_cat

            is_c, c_name, c_code = is_country_label(g_name)
            if not is_c:
                c_name, c_code = 'Global', 'GL'

            for item in group.get('items', []):
                raw_price = item.get('price')
                if raw_price is None or float(raw_price) <= 0:
                    continue
                cost_usd = float(raw_price)
                if cost_usd < min_cost:
                    continue

                retail_price = round(cost_usd * (1 + (markup / 100)), 2)
                sku = f"FR-{hashlib.md5(item['id'].encode()).hexdigest()[:8].upper()}"

                # Apply Law 2: Single-Region Distinct Plan Separation
                if b_name_lower == 'payment cards for services' or (item_cat == 'Subscriptions' and c_name == 'Global'):
                    cur_prod_title, label = split_service_item(item_brand, item['name'])
                else:
                    cur_prod_title = base_prod_title
                    label = extract_label(item['name'], b_name, g_name)

                variation_rows.append({
                    'category': item_cat,
                    'brand': item_brand,
                    'product_name': cur_prod_title,
                    'is_master_product': 'No',
                    'country_name': c_name,
                    'country_code': c_code,
                    'variation_name': label,
                    'cost_price': cost_usd,
                    'retail_price': retail_price,
                    'stock': int(item.get('quantity') or 999),
                    'sku': sku,
                    'provider_slug': 'foxreload',
                    'external_item_id': item['id'],
                    'external_category_id': group.get('category_id') or '',
                    'status': 1,
                    'thumbnail': 'product.png'
                })

    return variation_rows

# ==============================================================================
# 6. EXCEL COMPILER
# ==============================================================================
COLUMNS = [
    ('category', 'Category', 18),
    ('brand', 'Brand', 20),
    ('product_name', 'Product Name', 28),
    ('is_master_product', 'Is Master Product', 18),
    ('country_name', 'Country Name', 18),
    ('country_code', 'Country Code', 14),
    ('variation_name', 'Variation Name', 25),
    ('cost_price', 'Wholesale Cost (USD)', 20),
    ('retail_price', 'Retail Price (USD)', 20),
    ('stock', 'Stock', 12),
    ('sku', 'SKU', 18),
    ('provider_slug', 'Provider Slug', 15),
    ('external_item_id', 'External Item ID', 38),
    ('external_category_id', 'External Category ID', 38),
    ('status', 'Status', 10),
    ('thumbnail', 'Thumbnail', 16),
]

def compile_excel_catalog(rows, output_path='foxreload_direct_catalog.xlsx'):
    print(f"\n📑 Compiling {len(rows)} variation rows into Excel: {output_path}...")
    wb = openpyxl.Workbook()
    ws = wb.active
    ws.title = "Products Catalog"
    ws.views.sheetView[0].showGridLines = True

    header_fill = PatternFill(start_color="1E293B", end_color="1E293B", fill_type="solid")
    header_font = Font(name="Segoe UI", size=11, bold=True, color="FFFFFF")
    data_font = Font(name="Segoe UI", size=10, color="0F172A")
    number_font = Font(name="Segoe UI", size=10, color="047857", bold=True)
    border_thin = Border(
        left=Side(style='thin', color='E2E8F0'),
        right=Side(style='thin', color='E2E8F0'),
        top=Side(style='thin', color='E2E8F0'),
        bottom=Side(style='thin', color='E2E8F0')
    )
    zebra_fill = PatternFill(start_color="F8FAFC", end_color="F8FAFC", fill_type="solid")

    # Header Row
    for col_idx, (col_key, col_title, width) in enumerate(COLUMNS, 1):
        cell = ws.cell(row=1, column=col_idx, value=col_title)
        cell.fill = header_fill
        cell.font = header_font
        cell.alignment = Alignment(horizontal='center', vertical='center', wrap_text=True)
        ws.column_dimensions[get_column_letter(col_idx)].width = width
    ws.row_dimensions[1].height = 28

    # Rows
    for r_idx, row in enumerate(rows, 2):
        is_even = (r_idx % 2 == 0)
        ws.row_dimensions[r_idx].height = 20

        for c_idx, (col_key, _, _) in enumerate(COLUMNS, 1):
            val = row.get(col_key, '')
            cell = ws.cell(row=r_idx, column=c_idx, value=val)
            cell.border = border_thin
            cell.font = data_font

            if not is_even:
                cell.fill = zebra_fill

            if col_key in ['cost_price', 'retail_price']:
                cell.number_format = '$#,##0.00'
                cell.alignment = Alignment(horizontal='right', vertical='center')
                if col_key == 'retail_price':
                    cell.font = number_font
            elif col_key in ['stock', 'status']:
                cell.number_format = '0'
                cell.alignment = Alignment(horizontal='center', vertical='center')
            elif col_key in ['is_master_product', 'country_code']:
                cell.alignment = Alignment(horizontal='center', vertical='center')
            else:
                cell.alignment = Alignment(horizontal='left', vertical='center')

    # Data Validation
    dv_master = DataValidation(type="list", formula1='"Yes,No"', allow_blank=False)
    dv_status = DataValidation(type="list", formula1='"1,0"', allow_blank=False)
    max_row = max(len(rows) + 1, 100)
    dv_master.add(f"D2:D{max_row}")
    dv_status.add(f"O2:O{max_row}")
    ws.add_data_validation(dv_master)
    ws.add_data_validation(dv_status)

    ws.freeze_panes = "C2"
    ws.auto_filter.ref = f"A1:{get_column_letter(len(COLUMNS))}{len(rows) + 1}"

    # Instructions Sheet
    ws_info = wb.create_sheet(title="Instructions & Guide")
    ws_info.views.sheetView[0].showGridLines = True
    ws_info.column_dimensions['A'].width = 25
    ws_info.column_dimensions['B'].width = 85

    info_data = [
        ("Column", "Guide & Description"),
        ("Category", "Canonical store category: Gaming Cards, Gift Cards, Subscriptions, Digital Services, Telecom & eSIM."),
        ("Brand", "Brand name (e.g., Apple iTunes, Netflix, PUBG, Razer Gold, Adidas)."),
        ("Product Name", "Display name of the product page on the store."),
        ("Is Master Product", "Set to 'Yes' to consolidate all countries into a single product page with a Country Dropdown.\nSet to 'No' to keep this as an individual distinct product page."),
        ("Country Name", "Target country/region (e.g. Saudi Arabia, UAE, USA, Global)."),
        ("Country Code", "2-letter ISO country code (e.g. SA, AE, US, GL)."),
        ("Variation Name", "Card package or denomination (e.g. 50 SAR, $10, 660 UC)."),
        ("Wholesale Cost (USD)", "The base purchase cost from FoxReload."),
        ("Retail Price (USD)", "The customer selling price on your store. Easily edit or apply Excel formulas!"),
        ("Stock", "Stock quantity (default: 999)."),
        ("SKU", "Unique internal SKU."),
        ("Provider Slug", "Fulfillment provider (default: 'foxreload')."),
        ("External Item ID", "FoxReload product UUID (Crucial: used for automated instant digital delivery upon customer checkout)."),
        ("External Category ID", "FoxReload category UUID."),
        ("Status", "1 = Active / Published on store, 0 = Inactive / Hidden."),
        ("Thumbnail", "Default image filename (e.g. product.png)."),
    ]

    for r_i, (k, v) in enumerate(info_data, 1):
        c1 = ws_info.cell(row=r_i, column=1, value=k)
        c2 = ws_info.cell(row=r_i, column=2, value=v)
        c1.border = border_thin
        c2.border = border_thin
        if r_i == 1:
            c1.fill = header_fill
            c1.font = header_font
            c2.fill = header_fill
            c2.font = header_font
        else:
            c1.font = Font(name="Segoe UI", size=10, bold=True)
            c2.font = data_font
            c2.alignment = Alignment(wrap_text=True)

    wb.save(output_path)
    print(f"🎉 Successfully created Excel file at: {output_path} ({len(rows)} variation rows)")

# ==============================================================================
# 7. MAIN CRAWLER CONTROLLER
# ==============================================================================
def run_direct_dumper():
    parser = argparse.ArgumentParser(description="Directly crawl FoxReload API and export to Excel (.xlsx)")
    parser.add_argument('--output', default='foxreload_direct_catalog.xlsx', help="Target output Excel file")
    parser.add_argument('--root', default='all', help="Specific root category (e.g. gift-cards, games, subscriptions, top-ups, esim, or all)")
    parser.add_argument('--max-brands', type=int, default=None, help="Limit number of brands per root category (for quick testing)")
    parser.add_argument('--min-cost', type=float, default=0.20, help="Minimum wholesale cost in USD")
    parser.add_argument('--markup', type=float, default=ENV['FOXRELOAD_MARKUP_PERCENT'], help="Selling price markup percentage (default: 7%)")
    parser.add_argument('--in-stock-only', action='store_true', default=True, help="Only export items with active stock")
    parser.add_argument('--workers', type=int, default=8, help="Concurrent worker threads (default: 8)")
    args = parser.parse_args()

    print("=" * 70)
    print("  🚀 FOXRELOAD DIRECT-TO-EXCEL CATALOG DUMPER")
    print(f"  • API Base URL:  {ENV['FOXRELOAD_BASE_URL']}")
    print(f"  • Markup:        {args.markup}%")
    print(f"  • Min Cost:      ${args.min_cost:.2f}")
    print(f"  • In-Stock Only: {args.in_stock_only}")
    print(f"  • Workers:       {args.workers} threads")
    print(f"  • Output File:   {args.output}")
    print("=" * 70)

    # 1. Fetch Root Categories
    print("\n🔍 Step 1: Querying FoxReload root categories...")
    root_cats = api_get('/categories/').get('items', [])
    if not root_cats:
        print("❌ Error: Could not connect to FoxReload API or no categories returned.")
        sys.exit(1)

    # Filter out TEST ORDERS
    valid_roots = [r for r in root_cats if 'test' not in r.get('name', '').lower()]
    
    # Optional root filtering
    if args.root and args.root != 'all':
        filter_str = args.root.lower().replace('-', ' ')
        valid_roots = [r for r in valid_roots if filter_str in r.get('name', '').lower()]

    print(f"✅ Found {len(valid_roots)} active root categories:")
    for r in valid_roots:
        print(f"   • {r['name']} (ID: {r['id']} | inStock: {r.get('inStockCount', 0)})")

    # 2. Collect Brands across selected Root Categories
    print("\n🔍 Step 2: Collecting brands under root categories...")
    tasks = [] # (brand_item, root_name)

    for r in valid_roots:
        root_name = r['name']
        print(f"   📂 Fetching brands under '{root_name}'...", end='', flush=True)
        brands = fetch_all_categories(
            parent_id=r['id'],
            limit=100,
            max_items=args.max_brands,
            in_stock_only=args.in_stock_only
        )
        print(f" found {len(brands)} brands")
        for b in brands:
            tasks.append((b, root_name))

    print(f"\n📊 Total brands to crawl: {len(tasks)}")

    # 3. Concurrent Crawling of Products & Variations
    print(f"\n⚡ Step 3: Fetching subcategories & items concurrently ({args.workers} threads)...")
    all_rows = []
    completed = 0
    total = len(tasks)

    with ThreadPoolExecutor(max_workers=args.workers) as executor:
        future_to_brand = {
            executor.submit(process_single_brand, b_item, r_name, args.markup, args.min_cost, args.in_stock_only): b_item['name']
            for b_item, r_name in tasks
        }

        for future in as_completed(future_to_brand):
            b_title = future_to_brand[future]
            completed += 1
            try:
                rows = future.result()
                if rows:
                    all_rows.extend(rows)
                    print(f"[{completed}/{total}] ✅ {b_title} ({len(rows)} variations)")
                else:
                    print(f"[{completed}/{total}] ⚠️  {b_title} (no items)")
            except Exception as e:
                print(f"[{completed}/{total}] ❌ {b_title} error: {e}")

    # 4. Compile into Master Excel file
    if all_rows:
        compile_excel_catalog(all_rows, output_path=args.output)
    else:
        print("⚠️ No variations collected. Please check filters or stock availability.")

if __name__ == '__main__':
    run_direct_dumper()

#!/usr/bin/env python3
"""
FoxReload API Python Bridge Client
Provides reliable, SSL-error-free communication with FoxReload API.
"""

import sys
import os
import json
import requests

# Ensure UTF-8 output
try:
    sys.stdout.reconfigure(encoding='utf-8')
except AttributeError:
    pass

def load_env_file():
    """Load API key and base url from .env if present."""
    config = {
        'API_KEY': os.environ.get('FOXRELOAD_API_KEY', 'dmk_NzUT8k3M-sTu5Vg5dEyjiyEMED2qhOoA-ngnrBGQDiY'),
        'BASE_URL': os.environ.get('FOXRELOAD_BASE_URL', 'https://public-api.foxreload.com/api'),
        'CURRENCY': os.environ.get('FOXRELOAD_CURRENCY', 'usd'),
        'LANGUAGE': os.environ.get('FOXRELOAD_LANGUAGE', 'en'),
    }

    # Search for .env in current and parent dirs
    search_paths = [
        os.path.join(os.path.dirname(__file__), '../../../../.env'),
        os.path.join(os.path.dirname(__file__), '.env'),
        '.env'
    ]
    for p in search_paths:
        if os.path.exists(p):
            with open(p, 'r', encoding='utf-8', errors='ignore') as f:
                for line in f:
                    line = line.strip()
                    if line.startswith('#') or '=' not in line:
                        continue
                    k, v = line.split('=', 1)
                    k = k.strip()
                    v = v.strip().strip('"').strip("'")
                    if k == 'FOXRELOAD_API_KEY' and v:
                        config['API_KEY'] = v
                    elif k == 'FOXRELOAD_BASE_URL' and v:
                        config['BASE_URL'] = v
                    elif k == 'FOXRELOAD_CURRENCY' and v:
                        config['CURRENCY'] = v
                    elif k == 'FOXRELOAD_LANGUAGE' and v:
                        config['LANGUAGE'] = v
            break

    return config


CONFIG = load_env_file()

HEADERS = {
    'X-API-Key': CONFIG['API_KEY'],
    'X-Currency': CONFIG['CURRENCY'].lower(),
    'X-Language': CONFIG['LANGUAGE'].lower(),
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
}


def test_connection():
    try:
        url = f"{CONFIG['BASE_URL']}/categories/?limit=1"
        res = requests.get(url, headers=HEADERS, timeout=15)
        return {"success": res.status_code == 200, "status": res.status_code}
    except Exception as e:
        return {"success": False, "error": str(e)}


def get_categories(parent_id=None, limit=50, cursor=None):
    try:
        url = f"{CONFIG['BASE_URL']}/categories/?limit={limit}"
        if parent_id:
            url += f"&parentId={parent_id}"
        if cursor:
            url += f"&cursor={cursor}"
        res = requests.get(url, headers=HEADERS, timeout=25)
        if res.status_code == 200:
            return res.json()
        return {"items": [], "error": res.text, "status": res.status_code}
    except Exception as e:
        return {"items": [], "error": str(e)}


def get_products(category_id, limit=50, offset=0, with_stock_only=True):
    try:
        url = f"{CONFIG['BASE_URL']}/products/?categoryId={category_id}&limit={limit}&offset={offset}"
        if with_stock_only:
            url += "&withStockOnly=true"
        res = requests.get(url, headers=HEADERS, timeout=25)
        if res.status_code == 200:
            return res.json()
        return {"items": [], "error": res.text, "status": res.status_code}
    except Exception as e:
        return {"items": [], "error": str(e)}


def get_category(category_id):
    try:
        url = f"{CONFIG['BASE_URL']}/categories/{category_id}"
        res = requests.get(url, headers=HEADERS, timeout=20)
        if res.status_code == 200:
            return res.json()
        return None
    except Exception:
        return None


def get_category_products_grouped(category_id, limit=50, with_stock_only=True, allowed_subcategories=None):
    try:
        # 1. Direct products
        direct = get_products(category_id, limit=limit, with_stock_only=with_stock_only)
        direct_items = direct.get("items", [])
        if direct_items:
            cat = get_category(category_id)
            return [
                {
                    "category_id": category_id,
                    "category_name": cat.get("name") if cat else None,
                    "is_subcategory": False,
                    "items": direct_items
                }
            ]

        # 2. Check child subcategories
        children = get_categories(parent_id=category_id, limit=50)
        child_categories = children.get("items", [])

        groups = []
        for child in child_categories:
            if with_stock_only and child.get("inStockCount", 0) == 0:
                continue

            child_name = child.get("name", "")
            if allowed_subcategories:
                matched = any(allowed.lower() in child_name.lower() for allowed in allowed_subcategories)
                if not matched:
                    continue

            p = get_products(child["id"], limit=limit, with_stock_only=with_stock_only)
            items = p.get("items", [])
            if items:
                groups.append({
                    "category_id": child["id"],
                    "category_name": child_name,
                    "is_subcategory": True,
                    "items": items
                })

        return groups
    except Exception as e:
        return []


def get_balances():
    try:
        url = f"{CONFIG['BASE_URL']}/access/me/balances/"
        res = requests.get(url, headers=HEADERS, timeout=20)
        if res.status_code == 200:
            return {"success": True, "data": res.json()}
        return {"success": False, "status": res.status_code, "error": res.text}
    except Exception as e:
        return {"success": False, "error": str(e)}


def create_order(items, is_mock=True, idempotency_key=None):
    try:
        url = f"{CONFIG['BASE_URL']}/orders/"
        payload = {"items": items, "isMock": is_mock}
        if idempotency_key:
            payload["idempotencyKey"] = str(idempotency_key)
        res = requests.post(url, json=payload, headers=HEADERS, timeout=30)
        if res.status_code in [200, 201]:
            return {"success": True, "data": res.json()}
        return {"success": False, "status": res.status_code, "error": res.json() if res.headers.get("content-type", "").startswith("application/json") else res.text}
    except Exception as e:
        return {"success": False, "error": str(e)}


def pay_order(order_id, payment_provider=None, payment_method_type=None):
    try:
        url = f"{CONFIG['BASE_URL']}/orders/{order_id}/pay/"
        payload = {}
        if payment_provider and payment_provider != "null":
            payload["paymentProvider"] = payment_provider
        if payment_method_type and payment_method_type != "null":
            payload["paymentMethodType"] = payment_method_type
        res = requests.post(url, json=payload, headers=HEADERS, timeout=30)
        if res.status_code in [200, 201]:
            return {"success": True, "data": res.json()}
        return {"success": False, "status": res.status_code, "error": res.text}
    except Exception as e:
        return {"success": False, "error": str(e)}


def get_order_status(order_id):
    try:
        url = f"{CONFIG['BASE_URL']}/orders/{order_id}/"
        res = requests.get(url, headers=HEADERS, timeout=25)
        if res.status_code == 200:
            return {"success": True, "data": res.json()}
        return {"success": False, "status": res.status_code, "error": res.text}
    except Exception as e:
        return {"success": False, "error": str(e)}


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"error": "No action specified"}))
        sys.exit(1)

    action = sys.argv[1]

    if action == "test-connection":
        print(json.dumps(test_connection()))
    elif action == "get-balances":
        print(json.dumps(get_balances()))
    elif action == "get-categories":
        parent_id = sys.argv[2] if len(sys.argv) > 2 and sys.argv[2] != "null" else None
        limit = int(sys.argv[3]) if len(sys.argv) > 3 else 50
        print(json.dumps(get_categories(parent_id=parent_id, limit=limit)))
    elif action == "get-products":
        category_id = sys.argv[2]
        limit = int(sys.argv[3]) if len(sys.argv) > 3 else 50
        with_stock_only = sys.argv[4].lower() == "true" if len(sys.argv) > 4 else True
        print(json.dumps(get_products(category_id, limit=limit, with_stock_only=with_stock_only)))
    elif action == "get-category":
        category_id = sys.argv[2]
        print(json.dumps(get_category(category_id)))
    elif action == "get-grouped":
        category_id = sys.argv[2]
        limit = int(sys.argv[3]) if len(sys.argv) > 3 else 50
        with_stock_only = sys.argv[4].lower() == "true" if len(sys.argv) > 4 else True
        allowed = json.loads(sys.argv[5]) if len(sys.argv) > 5 and sys.argv[5] != "null" else None
        print(json.dumps(get_category_products_grouped(category_id, limit=limit, with_stock_only=with_stock_only, allowed_subcategories=allowed)))
    elif action == "create-order":
        items = json.loads(sys.argv[2])
        is_mock = sys.argv[3].lower() == "true" if len(sys.argv) > 3 else True
        idempotency_key = sys.argv[4] if len(sys.argv) > 4 and sys.argv[4] != "null" else None
        print(json.dumps(create_order(items, is_mock=is_mock, idempotency_key=idempotency_key)))
    elif action == "pay-order":
        order_id = sys.argv[2]
        provider = sys.argv[3] if len(sys.argv) > 3 and sys.argv[3] != "null" else None
        method = sys.argv[4] if len(sys.argv) > 4 and sys.argv[4] != "null" else None
        print(json.dumps(pay_order(order_id, payment_provider=provider, payment_method_type=method)))
    elif action == "get-order-status":
        order_id = sys.argv[2]
        print(json.dumps(get_order_status(order_id)))
    else:
        print(json.dumps({"error": f"Unknown action: {action}"}))
        sys.exit(1)


if __name__ == "__main__":
    main()

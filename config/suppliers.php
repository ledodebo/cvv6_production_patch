<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Digital Goods Supplier Driver
    |--------------------------------------------------------------------------
    |
    | This value determines the default supplier integration used to fetch
    | products, sync catalog, and fulfill instant digital orders.
    |
    */
    'default' => env('DEFAULT_SUPPLIER', 'foxreload'),

    /*
    |--------------------------------------------------------------------------
    | Digital Goods Suppliers Configuration
    |--------------------------------------------------------------------------
    |
    | Here you can configure connection details, API endpoints, retry policies,
    | and timeouts for each supported digital goods supplier.
    |
    */
    'suppliers' => [

        'foxreload' => [
            'name' => 'FoxReload Public API',
            'driver' => App\Services\Supplier\FoxReload\FoxReloadClient::class,

            // Connection Settings
            'base_url' => env('FOXRELOAD_BASE_URL', 'https://public-api.foxreload.com/api'),
            'api_key'  => env('FOXRELOAD_API_KEY'),

            // Request Headers & Preferences
            'headers' => [
                'language' => env('FOXRELOAD_LANGUAGE', 'en'),
                'currency' => env('FOXRELOAD_CURRENCY', 'usd'),
            ],

            // Resilience & Reliability Policies
            'timeout' => env('FOXRELOAD_TIMEOUT', 30), // Seconds
            'retry' => [
                'max_attempts' => env('FOXRELOAD_RETRY_MAX', 3),
                'initial_delay_ms' => 500,
                'multiplier' => 2.0,
            ],

            // Testing & Mock Mode
            'is_mock' => env('FOXRELOAD_IS_MOCK', true),

            // Pricing Rules
            'markup_percent' => env('FOXRELOAD_MARKUP_PERCENT', 7.0),

            // Variation Configuration (6valley Native Choice Options)
            'variation_attribute_name' => 'Package',
            'group_variations_by_default' => true,

            // Safety Thresholds
            'balance_alert_threshold_usd' => env('FOXRELOAD_LOW_BALANCE_ALERT', 50.00),

            /*
            |--------------------------------------------------------------------------
            | Curated Famous Games & Digital Gift Cards Catalog
            |--------------------------------------------------------------------------
            */
            'curated_brands' => [
                // --- TOP MIDDLE EAST FOCUSED DIGITAL GOODS ---
                // Yalla Ludo (Board Game #1 in MENA)
                'yalla_ludo' => [
                    'name' => 'Yalla Ludo',
                    'slug' => 'yalla-ludo',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '77698d34-7751-41d6-aeed-2041a92f5261',
                    ],
                ],

                // Tarbi3ah Baloot (Saudi Arabia Baloot)
                'tarbi3ah_baloot' => [
                    'name' => 'Tarbi3ah Baloot',
                    'slug' => 'tarbi3ah-baloot',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019f5090-0001-7000-8000-1a2b3c4d5e02',
                    ],
                ],

                // Nida Al Harb (Arabic Call of War)
                'nida_al_harb' => [
                    'name' => 'Nida Al Harb',
                    'slug' => 'nida-al-harb',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '67a5899c-4ff4-4d03-bdc7-cd6e8370cd00',
                    ],
                ],

                // Hello Yo
                'hello_yo' => [
                    'name' => 'Hello Yo',
                    'slug' => 'hello-yo',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        'f199ec7d-366a-403e-8991-50e3925d8861',
                    ],
                ],

                // Bigo Live
                'bigo_live' => [
                    'name' => 'Bigo Live',
                    'slug' => 'bigo-live',
                    'category' => 'Digital Services',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019e9326-aff9-75c1-9f30-c9db0ae6e0e0',
                    ],
                ],

                // Soulchill
                'soulchill' => [
                    'name' => 'Soulchill',
                    'slug' => 'soulchill',
                    'category' => 'Digital Services',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '2053de43-46f7-4055-931f-ba448e3307c0',
                    ],
                ],

                // Weyyak (Arabic VOD)
                'weyyak' => [
                    'name' => 'Weyyak',
                    'slug' => 'weyyak',
                    'category' => 'Subscriptions',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        'e54077ac-2ae4-47c4-84bf-370a7ad9a35c',
                    ],
                ],

                // podU (Arabic Podcasts)
                'podu' => [
                    'name' => 'podU',
                    'slug' => 'podu',
                    'category' => 'Subscriptions',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        'c84ba3f8-4126-4b46-9098-424f55812037',
                    ],
                ],

                // Netflix
                'netflix' => [
                    'name' => 'Netflix',
                    'slug' => 'netflix',
                    'category' => 'Subscriptions',
                    'group_variations' => true,
                    'allowed_subcategories' => ['Saudi Arabia', 'UAE', 'USA', 'United Kingdom', 'Europe', 'Egypt', 'Turkey'],
                    'foxreload_category_ids' => [
                        '019dbee3-2fb8-7a51-a751-f80f4ae41c36',
                    ],
                ],

                // 1. PUBG
                'pubg' => [
                    'name' => 'PUBG',
                    'slug' => 'pubg-mobile',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        'ba2d768f-4acc-495b-9c01-42ce45eeef74', // PUBG UC (Mobile)
                        'da74aa39-9466-4aa4-abf6-9a6c41f84000', // PUBG G-Coin (Steam / PC)
                    ],
                ],

                // 2. Free Fire
                'free_fire' => [
                    'name' => 'Free Fire',
                    'slug' => 'free-fire',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        'da6358b5-11f0-4e4d-98cf-1e7e6c82d3e1', // Free Fire Diamonds (Global)
                        '019f5119-c19d-7cc0-bb96-e543e9eea3a9', // Free Fire MENA
                    ],
                ],

                // 3. Brawl Stars
                'brawl_stars' => [
                    'name' => 'Brawl Stars',
                    'slug' => 'brawl-stars',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '5aaefc68-999a-4a59-8071-6425de6b5f35', // Brawl Stars Gems (Global)
                        '67ce52c7-861b-4a61-a35c-9f4981ee902f', // Brawl Pass
                    ],
                ],

                // 4. Roblox
                'roblox' => [
                    'name' => 'Roblox',
                    'slug' => 'roblox',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '01a05baf-7cfa-72a0-bdb4-2987737552fd', // Roblox USD Global
                    ],
                ],

                // 5. Call of Duty
                'call_of_duty' => [
                    'name' => 'Call of Duty',
                    'slug' => 'call-of-duty',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019e9326-aff9-75c1-9f30-cae5c330d4a8', // Call of Duty Points
                    ],
                ],

                // 6. Mobile Legends
                'mobile_legends' => [
                    'name' => 'Mobile Legends',
                    'slug' => 'mobile-legends',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019e9606-67d3-70f1-8430-d9efec6dc664', // Mobile Legends Diamonds (Global)
                    ],
                ],

                // 7. Clash of Clans
                'clash_of_clans' => [
                    'name' => 'Clash of Clans',
                    'slug' => 'clash-of-clans',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019fb40e-7c8f-7ba3-8049-44bbb9a39362', // Clash of Clans Gems
                    ],
                ],

                // 8. Minecraft
                'minecraft' => [
                    'name' => 'Minecraft',
                    'slug' => 'minecraft',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019ad428-ea76-7672-a104-ee32a4955fbc', // Minecraft Minecoins & Editions
                    ],
                ],

                // 9. League of Legends / Valorant
                'riot_games' => [
                    'name' => 'League of Legends & Valorant',
                    'slug' => 'league-of-legends',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'allowed_subcategories' => ['Global', 'USA', 'Europe', 'United Kingdom', 'Australia', 'UAE'],
                    'foxreload_category_ids' => [
                        '019e4140-f5bf-7f20-922b-9042ce671234', // Riot Points / Valorant
                    ],
                ],

                // 10. Fortnite
                'fortnite' => [
                    'name' => 'Fortnite',
                    'slug' => 'fortnite',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'allowed_subcategories' => ['Global', 'USA', 'United Kingdom', 'Saudi Arabia', 'UAE', 'Europe'],
                    'foxreload_category_ids' => [
                        '8a87a6df-9473-4cd1-b762-0ce90a6299ae', // Fortnite V-Bucks
                    ],
                ],

                // 11. EA Sports FC (FIFA)
                'ea_sports_fc' => [
                    'name' => 'EA Sports FC Points',
                    'slug' => 'ea-sports-fc',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'allowed_subcategories' => ['PC Points', 'Xbox', 'Saudi Arabia', 'UAE'],
                    'foxreload_category_ids' => [
                        'd27a579f-64dd-4b31-9b0a-02cc0ea394e9', // FC 25 Points
                        '019f88ff-0001-7000-8000-5e1fc0de0001', // FC Mobile
                    ],
                ],

                // 12. GTA V Online
                'gta_v' => [
                    'name' => 'GTA V',
                    'slug' => 'gta-v',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019ad428-e7a0-7d73-b70b-b292f199abf2', // GTA Online Shark Cards
                    ],
                ],

                // 13. Apex Legends
                'apex_legends' => [
                    'name' => 'Apex Legends',
                    'slug' => 'apex-legends',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019ad428-ecde-7b91-bebd-40aeb6723153', // Apex Coins
                    ],
                ],

                // 14. Garena Shells
                'garena' => [
                    'name' => 'Garena Shells',
                    'slug' => 'garena-shells',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'allowed_subcategories' => ['Global', 'USA', 'Malaysia'],
                    'foxreload_category_ids' => [
                        '019e9326-aff9-75c1-9f30-ca49e31c6231', // Garena Shells
                    ],
                ],

                // 15. Jawaker
                'jawaker' => [
                    'name' => 'Jawaker',
                    'slug' => 'jawaker',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'allowed_subcategories' => ['Global', 'Gift Card'],
                    'foxreload_category_ids' => [
                        '62065e7b-abc1-44a6-87a4-e64c205f4aee', // Jawaker Tokens
                    ],
                ],

                // 16. World of Warcraft
                'world_of_warcraft' => [
                    'name' => 'World of Warcraft',
                    'slug' => 'world-of-warcraft',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019c167f-dd33-7c43-92a8-ad3f20f0341e', // WoW Game Time
                    ],
                ],

                // 17. RuneScape / OSRS
                'runescape' => [
                    'name' => 'RuneScape',
                    'slug' => 'runescape',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'allowed_subcategories' => ['Global', 'Europe', 'USA', 'North America'],
                    'foxreload_category_ids' => [
                        '019fdd00-0001-7000-8000-1a2b3c4d0001', // RuneScape Membership
                        '019fdd00-0001-7000-8000-1a2b3c4d0002', // OSRS Membership
                    ],
                ],

                // 18. Candy Crush
                'candy_crush' => [
                    'name' => 'Candy Crush',
                    'slug' => 'candy-crush',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '1796a8ff-2990-46b6-8235-fa589295b03c', // Candy Crush Gold Bars
                    ],
                ],

                // 19. Black Clover M
                'black_clover' => [
                    'name' => 'Black Clover M',
                    'slug' => 'black-clover-m',
                    'category' => 'Gaming Cards',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '39ca507f-3fb6-49fe-aeba-51be24c86b02', // Black Clover M Redeem Card
                    ],
                ],

                // 20. Amazon Gift Cards
                'amazon' => [
                    'name' => 'Amazon',
                    'slug' => 'amazon-gift-cards',
                    'category' => 'Gift Cards',
                    'group_variations' => true,
                    'allowed_subcategories' => ['USA', 'Saudi Arabia', 'UAE', 'United Kingdom', 'Germany'],
                    'foxreload_category_ids' => [
                        '019e9326-aff9-75c1-9f30-c97cb4c7572d', // Amazon Gift Cards
                    ],
                ],

                // 21. Apple iTunes / App Store
                'apple' => [
                    'name' => 'Apple iTunes',
                    'slug' => 'apple-itunes',
                    'category' => 'Gift Cards',
                    'group_variations' => true,
                    'allowed_subcategories' => ['USA', 'Saudi Arabia', 'UAE', 'United Kingdom'],
                    'foxreload_category_ids' => [
                        '019ad428-e7d9-7bc2-9eca-ddaeec623043', // Apple iTunes Gift Cards
                    ],
                ],

                // 22. Google Play
                'google_play' => [
                    'name' => 'Google Play',
                    'slug' => 'google-play',
                    'category' => 'Gift Cards',
                    'group_variations' => true,
                    'allowed_subcategories' => ['USA', 'Saudi Arabia', 'UAE', 'United Kingdom'],
                    'foxreload_category_ids' => [
                        '019dbf2a-3ed1-7602-9c79-a7e366f76593', // Google Play Gift Cards
                    ],
                ],

                // 23. Discord Nitro
                'discord' => [
                    'name' => 'Discord',
                    'slug' => 'discord',
                    'category' => 'Subscriptions',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019c303f-a899-7461-9ee3-c4693e8e0e0a', // Discord Nitro Subscriptions
                    ],
                ],

                // 24. Shahid VIP
                'shahid' => [
                    'name' => 'Shahid VIP',
                    'slug' => 'shahid-vip',
                    'category' => 'Subscriptions',
                    'group_variations' => true,
                    'foxreload_category_ids' => [
                        '019f5084-29ff-7178-9f8f-b6e89f3da9b6', // Shahid VIP Streaming
                    ],
                ],

                // 25. Tinder
                'tinder' => [
                    'name' => 'Tinder',
                    'slug' => 'tinder',
                    'category' => 'Subscriptions',
                    'group_variations' => true,
                    'allowed_subcategories' => ['USA', 'United Kingdom', 'Germany', 'Canada', 'Netherlands', 'Kuwait', 'Qatar', 'Egypt'],
                    'foxreload_category_ids' => [
                        '019e9326-aff9-75c1-9f30-c9c3746a99ff', // Tinder Plus/Gold
                    ],
                ],
            ],
        ],
    ],
];

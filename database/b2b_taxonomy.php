<?php
declare(strict_types=1);

// B2B export / sourcing taxonomy used by fresh installs and the one-time
// migration of the original local-services seed data.
return [
    'industries' => [
        // code, English name, Chinese name, description, parent code, sort order
        ['industrial-machinery', 'Industrial Machinery & Equipment', '工业机械与设备', '面向工业制造、工程与设备采购的 B2B 品类', null, 10],
        ['construction-machinery', 'Construction Machinery', '工程机械', '工程机械、建筑设备及配件', 'industrial-machinery', 11],
        ['industrial-equipment', 'Industrial Equipment', '工业设备', '工厂设备、生产线与工业自动化', 'industrial-machinery', 12],
        ['hardware-tools', 'Hardware & Tools', '五金工具', '手动工具、电动工具与五金配件', 'industrial-machinery', 13],
        ['molds-parts', 'Molds & Mechanical Parts', '模具与机械零部件', '模具、铸锻件与精密机械零部件', 'industrial-machinery', 14],

        ['automotive', 'Automotive & Parts', '汽车与配件', '汽车、商用车、摩托车及售后市场', null, 20],
        ['auto-parts', 'Auto Parts', '汽车零部件', '汽车零配件、易损件与汽车用品', 'automotive', 21],
        ['commercial-vehicles', 'Commercial Vehicles', '商用车', '卡车、客车、专用车辆及配件', 'automotive', 22],
        ['motorcycle-ev', 'Motorcycles & E-bikes', '摩托车与电动车', '摩托车、电动两轮车与配件', 'automotive', 23],

        ['electronics-appliances', 'Electronics & Appliances', '电子与电器', '消费电子、元器件与家用电器', null, 30],
        ['consumer-electronics', 'Consumer Electronics', '消费电子', '智能设备、数码产品与消费电子', 'electronics-appliances', 31],
        ['electronic-components', 'Electronic Components', '电子元器件', '芯片以外的电子元件、连接器与模组', 'electronics-appliances', 32],
        ['home-appliances', 'Home Appliances', '家用电器', '小家电、厨房电器与生活电器', 'electronics-appliances', 33],
        ['lighting-led', 'Lighting & LED', '照明与 LED', '商业照明、户外照明与 LED 产品', 'electronics-appliances', 34],

        ['building-home', 'Building Materials & Home', '建材与家居', '建筑材料、家具与家居装饰', null, 40],
        ['building-materials', 'Building Materials', '建筑材料', '石材、陶瓷、钢材、板材与建筑辅材', 'building-home', 41],
        ['bathroom-kitchen', 'Bathroom & Kitchen', '卫浴厨房', '卫浴、厨柜与厨房设备', 'building-home', 42],
        ['furniture', 'Furniture & Furnishings', '家具家居', '民用家具、商用家具与家居软装', 'building-home', 43],
        ['doors-windows', 'Doors, Windows & Decor', '门窗与装饰', '门窗、幕墙、地板与装饰材料', 'building-home', 44],

        ['packaging-printing', 'Packaging & Printing', '包装与印刷', '包装材料、包装机械与商业印刷', null, 50],
        ['packaging-materials', 'Packaging Materials', '包装材料', '纸包装、塑料包装与定制包装', 'packaging-printing', 51],
        ['printing-labels', 'Printing & Labels', '印刷与标签', '商业印刷、标签与标识产品', 'packaging-printing', 52],

        ['chemicals-plastics', 'Chemicals & Plastics', '化工与塑料', '化工原料、塑料与橡胶制品', null, 60],
        ['chemical-materials', 'Chemical Raw Materials', '化工原料', '精细化工、涂料与工业化学品', 'chemicals-plastics', 61],
        ['plastics-rubber', 'Plastics & Rubber', '塑料与橡胶', '塑料制品、橡胶制品与改性材料', 'chemicals-plastics', 62],

        ['textiles-apparel', 'Textiles & Apparel', '纺织与服装', '面辅料、服装与时尚配饰', null, 70],
        ['fabrics', 'Fabrics & Accessories', '面料与辅料', '面料、纱线、纽扣与服装辅料', 'textiles-apparel', 71],
        ['apparel', 'Apparel & Fashion', '服装与配饰', '成衣、运动服饰与时尚配饰', 'textiles-apparel', 72],

        ['beauty-personal-care', 'Beauty & Personal Care', '美妆与个护', '护肤、彩妆与个人护理产品', null, 80],
        ['skincare-cosmetics', 'Skincare & Cosmetics', '护肤与彩妆', '护肤、彩妆与香氛产品', 'beauty-personal-care', 81],
        ['personal-care', 'Personal Care', '个人护理', '洗护、口腔护理与日化用品', 'beauty-personal-care', 82],

        ['food-agriculture', 'Food & Agriculture', '食品与农业', '食品饮料、农产品与农业投入品', null, 90],
        ['food-beverage', 'Food & Beverage', '食品饮料', '休闲食品、饮料与食品原料', 'food-agriculture', 91],
        ['agricultural-products', 'Agricultural Products', '农产品', '谷物、果蔬、饲料与农业加工品', 'food-agriculture', 92],

        ['medical-health', 'Medical & Health', '医疗与健康', '医疗器械、康复与健康产品', null, 100],
        ['medical-devices', 'Medical Devices', '医疗器械', '医疗设备、耗材与诊断产品', 'medical-health', 101],
        ['health-rehab', 'Health & Rehabilitation', '健康与康复', '康复器材、养老与健康用品', 'medical-health', 102],

        ['new-energy-environment', 'New Energy & Environment', '新能源与环保', '光伏、储能与环保设备', null, 110],
        ['solar-storage', 'Solar & Energy Storage', '光伏与储能', '光伏组件、储能系统与逆变器', 'new-energy-environment', 111],
        ['environmental-equipment', 'Environmental Equipment', '环保设备', '水处理、固废与环保工程设备', 'new-energy-environment', 112],

        ['consumer-goods', 'Consumer Goods', '日用消费品', '家居日用、礼品与宠物用品', null, 120],
        ['houseware', 'Houseware', '家居用品', '餐厨、收纳与家居日用', 'consumer-goods', 121],
        ['gifts-crafts', 'Gifts & Crafts', '礼品与工艺品', '节庆礼品、工艺品与定制赠品', 'consumer-goods', 122],
        ['pet-supplies', 'Pet Supplies', '宠物用品', '宠物食品、用品与智能设备', 'consumer-goods', 123],

        ['trade-services', 'Cross-border Trade Services', '跨境贸易服务', '进出口、分销、物流与电商服务', null, 130],
        ['import-export', 'Import & Export Traders', '进出口贸易商', '进出口商、贸易公司与采购代理', 'trade-services', 131],
        ['wholesale-distribution', 'Wholesale & Distribution', '批发与分销', '批发商、区域代理与分销渠道', 'trade-services', 132],
        ['logistics', 'Logistics & Supply Chain', '物流与供应链', '货代、仓储与供应链服务', 'trade-services', 133],
        ['ecommerce-services', 'E-commerce Services', '电商与品牌服务', '跨境电商、代运营与履约服务', 'trade-services', 134],
    ],
    'keywords' => [
        ['industrial-equipment', 'DE', 'en', 'industrial machinery importers', 'B2B 工业设备采购商'],
        ['construction-machinery', 'AE', 'en', 'construction equipment distributors', '工程机械分销商'],
        ['hardware-tools', 'GB', 'en', 'hardware wholesalers', '五金批发商'],
        ['auto-parts', 'BR', 'pt', 'importadores de autopeças', '汽车零部件进口商'],
        ['auto-parts', 'US', 'en', 'auto parts distributors', '汽车零部件分销商'],
        ['consumer-electronics', 'SG', 'en', 'consumer electronics distributors', '消费电子批发商'],
        ['lighting-led', 'US', 'en', 'LED lighting distributors', 'LED 照明进口商'],
        ['building-materials', 'AE', 'en', 'building materials suppliers', '建材供应商'],
        ['furniture', 'US', 'en', 'furniture wholesalers', '家具进口商'],
        ['packaging-materials', 'FR', 'fr', "importateurs d'emballage", '包装材料进口商'],
        ['chemical-materials', 'NL', 'en', 'chemical distributors', '化工分销商'],
        ['plastics-rubber', 'DE', 'de', 'Kunststoff Großhändler', '塑料批发商'],
        ['fabrics', 'IT', 'it', 'importatori di tessuti', '面料进口商'],
        ['apparel', 'FR', 'fr', 'grossistes de vêtements', '服装批发商'],
        ['skincare-cosmetics', 'JP', 'ja', '化粧品 卸売', '化妆品批发商'],
        ['food-beverage', 'CA', 'en', 'food distributors', '食品进口商'],
        ['medical-devices', 'AU', 'en', 'medical device distributors', '医疗器械分销商'],
        ['solar-storage', 'ES', 'es', 'distribuidores de paneles solares', '光伏产品分销商'],
        ['pet-supplies', 'AU', 'en', 'pet product wholesalers', '宠物用品批发商'],
        ['wholesale-distribution', 'SG', 'en', 'B2B wholesale distributors', '批发分销商'],
        ['import-export', 'GB', 'en', 'import export trading companies', '进出口贸易公司'],
        ['logistics', 'NL', 'en', 'freight forwarders', '货运代理'],
        ['ecommerce-services', 'US', 'en', 'ecommerce fulfillment companies', '电商履约服务商'],
        ['gifts-crafts', 'IT', 'en', 'gift wholesalers', '礼品批发商'],
        ['houseware', 'JP', 'ja', '生活雑貨 卸売', '家居用品批发商'],
    ],
];

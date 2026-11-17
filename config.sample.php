<?php
/*
 * คัดลอกไฟล์นี้เป็น config.php แล้วแก้ค่าให้ตรงกับโฮสต์ของคุณ
 * (config.php ถูกใส่ไว้ใน .gitignore แล้ว — รหัสฐานข้อมูลจะไม่ขึ้น GitHub)
 */
return [
    // 'mysql' สำหรับ Hostatom / shared hosting ทั่วไป, 'sqlite' สำหรับทดสอบในเครื่อง
    'db_driver' => 'mysql',

    // MySQL / MariaDB — สร้างได้ใน DirectAdmin/cPanel > MySQL Management
    'db_host' => 'localhost',
    'db_name' => 'camp1_db',
    'db_user' => 'camp1_user',
    'db_pass' => 'CHANGE_ME',

    // ใช้เมื่อ db_driver = 'sqlite' เท่านั้น
    'sqlite_path' => __DIR__ . '/data/camp1.sqlite',

    'site_name'    => 'Camp 1 Express',
    'timezone'     => 'Asia/Bangkok',
    'default_lang' => 'th',

    // เรทและค่าขนส่ง (แสดงบนหน้าแรกและในเครื่องคิดค่าส่ง)
    'rate_yen'      => 0.235, // 1 เยน = ? บาท
    'ship_per_kg'   => 140,   // บาท / กิโลกรัม
    'ship_per_100g' => 14,    // บาท / ขีด (100 กรัม)
    'days_to_receive' => 30,

    // ข้อมูลติดต่อ
    'contact_phone'    => '082-720-8888',
    'contact_line'     => '@camp1express',
    'contact_line_url' => 'https://line.me/R/ti/p/@camp1express',
    'contact_facebook' => 'CAMP1 ฝากสั่ง/ประมูลสินค้า จากประเทศญี่ปุ่น',
    'contact_facebook_url' => '', // ใส่ลิงก์เพจ Facebook
];

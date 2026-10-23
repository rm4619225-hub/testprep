<?php
// ====== TestPrep PHP Engine — सिर्फ़ यही फ़ाइल एडिट करें / Edit only this file ======
define('ADMIN_TOKEN', 'TestPrep$S3cure#2002');          // एडमिन पैनल में यही टोकन डालें / paste this token in Admin panel
define('ALLOWED_ORIGIN', '*');                              // बेहतर सुरक्षा: 'https://yoursite.com'
define('DATA_DIR', __DIR__ . '/data');

// ---- ईमेल (बिल भेजने के लिए) / Mail ----
define('MAIL_FROM', 'rm4619225@gmail.com');
define('MAIL_FROM_NAME', 'TestPrep Billing');
define('SMTP_HOST', 'smtp.gmail.com');            // खाली = PHP mail(). Gmail: smtp.gmail.com
define('SMTP_PORT', 587);           // tls=587, ssl=465
define('SMTP_SECURE', 'tls');       // 'tls' | 'ssl' | ''
define('SMTP_USER', 'rm4619225@gmail.com');            // जैसे yourmail@gmail.com
define('SMTP_PASS', 'znda euxa uoih ordi');            // Gmail App Password (सामान्य पासवर्ड नहीं)

define('MAX_ATTEMPTS', 3);          // failed बिल पर कितनी बार retry
define('SITE_NAME', 'TestPrep');

// ---- MySQL (वैकल्पिक) — DB_NAME खाली = JSON फ़ाइलें (data/ फ़ोल्डर); भरा = MySQL ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'testprep');              // जैसे 'testprep'  (पहले database/schema.sql import करें)
define('DB_USER', 'root');
define('DB_PASS', '');

// ---- एडमिन OTP लॉगिन (वैकल्पिक) — OTP इसी मोबाइल नंबर पर SMS से आएगा ----
define('ADMIN_MOBILES', '6202401346');        // अधिकृत एडमिन नंबर, कॉमा से अलग: '9876543210,9123456789' (खाली = OTP लॉगिन बंद)
define('ADMIN_EMAIL', 'rm4619225@gmail.com');          // वैकल्पिक: OTP की कॉपी इस ईमेल पर भी (खाली = सिर्फ़ SMS)
define('SMS_PROVIDER', 'fast2sms'); // 'fast2sms' | 'twilio'
define('FAST2SMS_KEY', '');         // fast2sms.com → Dev API key
define('TWILIO_SID', '');           // Twilio Account SID
define('TWILIO_TOKEN', '');         // Twilio Auth Token
define('TWILIO_FROM', '');          // Twilio नंबर, जैसे '+1415XXXXXXX'

// ---- कार्ड/UPI/नेटबैंकिंग का असली गेटवे (Razorpay, वैकल्पिक) — खाली = डेमो मोड ----
define('RAZORPAY_KEY_ID', '');
define('RAZORPAY_KEY_SECRET', '');

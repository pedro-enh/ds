# 🤖 Discord Bot Setup Guide

## نظام البوت المكتمل

تم إنشاء نظام بوت Discord كامل يراقب القناة `1319029928825589780` ويرسل رسائل تأكيد للمستخدمين عند تحويل ProBot credits.

## 📁 الملفات المنشأة:

### 1. `discord-bot.php`
- البوت الرئيسي الذي يراقب القناة
- يكتشف تحويلات ProBot تلقائياً
- يرسل رسائل تأكيد للمستخدمين
- يضيف الكريديت للحسابات

### 2. `start-bot.php`
- ملف تشغيل البوت على Railway
- يحافظ على البوت يعمل باستمرار
- إعادة تشغيل تلقائية عند الأخطاء

### 3. `Procfile` (محدث)
- يشغل الموقع والبوت معاً
- `web`: الموقع
- `bot`: البوت

## 🔧 خطوات الإعداد:

### 1. إنشاء Discord Bot:
1. اذهب إلى https://discord.com/developers/applications
2. اضغط "New Application"
3. أدخل اسم: "Discord Broadcaster Pro Bot"
4. اذهب إلى "Bot" → "Add Bot"
5. انسخ **Bot Token**

### 2. إعداد الصلاحيات:
في "Bot" → "Privileged Gateway Intents":
- ✅ Message Content Intent
- ✅ Server Members Intent

في "OAuth2" → "URL Generator":
- **Scopes:** `bot`
- **Bot Permissions:**
  - ✅ Read Messages
  - ✅ Send Messages
  - ✅ Read Message History
  - ✅ Send Messages in Threads

### 3. دعوة البوت للسيرفر:
1. انسخ الرابط المُنشأ من OAuth2
2. ادعُ البوت للسيرفر الذي يحتوي على القناة `1319029928825589780`
3. تأكد من أن البوت يمكنه قراءة القناة

### 4. تحديث متغيرات البيئة في Zeabur:
```env
DISCORD_CLIENT_ID=your_client_id
DISCORD_CLIENT_SECRET=your_client_secret
DISCORD_BOT_TOKEN=your_bot_token_here
DISCORD_REDIRECT_URI=https://discord-brodcast.zeabur.app/complete-auth.php
```

### 5. إعادة النشر:
- في Zeabur Dashboard، اضغط "Deploy"
- البوت سيبدأ العمل تلقائياً

## 🎯 كيف يعمل النظام:

### 1. مراقبة القناة:
- البوت يراقب القناة `1319029928825589780` كل 5 ثوانٍ
- يبحث عن رسائل ProBot للتحويلات

### 2. كشف التحويلات:
- يكتشف رسائل مثل: "✅ transferred 5000 credits to <@675332512414695441>"
- يتحقق من أن المستلم هو `675332512414695441`

### 3. معالجة الدفع:
- يحسب الكريديت: 500 ProBot credits = 1 broadcast message
- يضيف الكريديت لحساب المستخدم
- يمنع المعالجة المكررة

### 4. إرسال التأكيد:
```
✅ Payment Successful!

💰 Received: 5000 ProBot Credits
📨 Added: 10 Broadcast Messages

🎉 Your credits have been added to your wallet!
🌐 Visit: https://discord-brodcast.zeabur.app/wallet.php
```

## 📊 مثال على التحويل:

### المستخدم يرسل:
```
/transfer @675332512414695441 5000
```

### ProBot يرد:
```
✅ Successfully transferred 5000 credits to <@675332512414695441>
```

### البوت يكتشف ويرسل للمستخدم:
```
✅ Payment Successful!
💰 Received: 5000 ProBot Credits
📨 Added: 10 Broadcast Messages
🎉 Your credits have been added to your wallet!
```

## 🔍 مراقبة البوت:

### في Zeabur Logs:
```
🤖 Discord Bot started monitoring channel: 1319029928825589780
📨 Processing ProBot message: ✅ Successfully transferred...
💰 Found transfer: 5000 credits to 675332512414695441
✅ Successfully processed transfer: 5000 ProBot credits → 10 broadcast messages
📤 Sent confirmation message to user 123456789
```

## ⚠️ ملاحظات مهمة:

1. **البوت يجب أن يكون في السيرفر** الذي يحتوي على القناة
2. **الصلاحيات مطلوبة** لقراءة الرسائل وإرسال DMs
3. **Bot Token سري** - لا تشاركه مع أحد
4. **المستخدم يجب أن يسجل** في الموقع أولاً لاستلام الكريديت

## 🚀 النظام جاهز!

بمجرد إضافة Bot Token في Zeabur، سيعمل النظام بالكامل:
- ✅ مراقبة تلقائية للقناة
- ✅ كشف تحويلات ProBot
- ✅ إضافة كريديت تلقائية
- ✅ رسائل تأكيد فورية
- ✅ منع المعالجة المكررة

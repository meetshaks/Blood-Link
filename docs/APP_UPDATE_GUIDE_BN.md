# BloodLink App Update: সহজ নির্দেশিকা

এই নির্দেশিকা সাইটে পরিবর্তন করার পর hosting-এ কী দিতে হবে এবং install করা app-এ update banner কীভাবে আসবে তা বোঝায়।

## আগে গুরুত্বপূর্ণ বিষয়টি বুঝুন

BloodLink-এর **“এখন আপডেট / পরে”** হলো app-এর ভেতরের update banner। এটি ফোনে পাঠানো push notification নয়।

ব্যবহারকারীর browser-কে নতুন Service Worker খুঁজে পেতে হবে। সাধারণত ব্যবহারকারী app/site online অবস্থায় খুললে বা আবার সামনে আনলে browser update check করে। নতুন worker প্রস্তুত হলে app-এর ভেতরে banner দেখা যায়। কখন check হবে তা browser ঠিক করে—সঙ্গে সঙ্গে সবার কাছে পৌঁছানোর নিশ্চয়তা নেই। App uninstall বা reinstall করতে হয় না।

## কোন পরিবর্তনে কী করবেন

| পরিবর্তনের ধরন | কী deploy করবেন | App update banner |
|---|---|---|
| শুধু PHP page-এর লেখা বা server-side logic | বদলানো PHP ফাইল | সাধারণত banner লাগবে না; পেজ আবার খুললে পরিবর্তন আসবে |
| শুধু ভাষার PHP text, যেমন `app/lang/bn.php` | বদলানো language file | সাধারণত banner লাগবে না |
| CSS, JavaScript, icon বা worker-এ precache করা asset | বদলানো asset এবং নিচের asset version/cache version ধাপগুলো | নতুন worker প্রস্তুত হলে banner আসবে |
| `public/sw.js`-এর caching বা update logic | `sw.js` এবং প্রয়োজনীয় অন্য ফাইল; `CACHE_VERSION` বাড়ান | নতুন worker পাওয়া গেলে banner আসবে |
| Manifest/app install metadata | manifest ও সংশ্লিষ্ট icon/metadata | Browser নিজে metadata refresh করতে পারে; update banner নিশ্চিত করার নিয়ম হিসেবে `sw.js`-ও পরিবর্তন ও deploy করুন |

কোনো পরিবর্তনে banner দরকার কি না নিশ্চিত না হলে, সংশ্লিষ্ট ফাইলগুলো দেখে release-এর আগে version মিলিয়ে নিন।

## উদাহরণ: CSS-এর রং বা ফন্ট বদলালে

ধরা যাক `public/assets/css/base.css`-এ রং বা font style বদলালেন। বর্তমান version:

- CSS asset: `v55`
- Service Worker cache: `v14`

পরের release-এ:

1. `app/views/layout/header.php`-তে stylesheet URL `base.css?v=55` থেকে `base.css?v=56` করুন।
2. `public/sw.js`-এর `STATIC_ASSETS` তালিকায় একই URL `assets/css/base.css?v=55` থেকে `assets/css/base.css?v=56` করুন।
3. `public/sw.js`-এর `CACHE_VERSION` `v14` থেকে `v15` করুন।
4. বদলানো `base.css`, `header.php`, এবং `sw.js`—তিনটিই hosting-এ deploy করুন।

দুই জায়গার CSS URL এক হওয়া জরুরি। এক জায়গায় version বদলে অন্য জায়গায় পুরোনোটি রাখলে Service Worker নতুন ফাইল precache করতে ব্যর্থ হতে পারে।

## JavaScript বা অন্য cached asset বদলালে

যে asset বদলেছেন, তার URL-এর version বাড়ান এবং একই URL দুই জায়গায় রাখুন:

- `app/views/layout/header.php` অথবা যে page ওই asset load করে
- `public/sw.js`-এর `STATIC_ASSETS` তালিকা

উদাহরণ: `pwa.js?v=9` → `pwa.js?v=10`। তারপর `public/sw.js`-এর `CACHE_VERSION`-ও এক ধাপ বাড়িয়ে, সব পরিবর্তিত ফাইল একসঙ্গে deploy করুন।

Asset-এর URL-এ version না থাকলে (যেমন `assets/icons/icon-192.png`) নতুন URL version যোগ করে সংশ্লিষ্ট page/manifest ও Service Worker-এর allowlist—দুই জায়গাতেই একই URL ব্যবহার করুন।

## Service Worker-এর নিজস্ব logic বদলালে

`public/sw.js`-এ caching, offline fallback, update বা অন্য worker logic বদলালে `CACHE_VERSION` অবশ্যই বাড়ান—যেমন `v14` → `v15`। Worker-এ যে asset URL-গুলো precache হয় সেগুলো deploy-এর সময় সত্যিই পাওয়া যাচ্ছে কি না যাচাই করুন।

`CACHE_VERSION` বাড়ালে নতুন নামে static/offline cache তৈরি হয়। নতুন worker activate হলে একই app scope-এর আগের version cache মুছে যায়। আলাদা browser profile বা অন্য device-এর cache এতে সঙ্গে সঙ্গে বদলায় না; তাদের browser-কে নতুন worker install/activate করতে হবে।

## Hosting-এ deploy করার ধাপ

1. Local project-এ প্রয়োজনীয় code/asset পরিবর্তন করুন।
2. পরিবর্তিত asset-এর URL version এবং `public/sw.js`-এর `CACHE_VERSION` নিয়মমতো বাড়ান।
3. `header.php`/page এবং `sw.js`-এর asset version একই কি না মিলিয়ে নিন।
4. সব বদলানো ফাইল আপনার live HTTPS hosting-এ upload/deploy করুন। শুধু নিজের কম্পিউটারে ফাইল edit করলে ব্যবহারকারীরা পরিবর্তন পাবেন না।
5. Hosting-এ `sw.js`, নতুন CSS/JS URL এবং প্রয়োজনীয় image URL খুলে সঠিক নতুন ফাইল আসছে কি না দেখুন।
6. Android Chrome বা iPhone Safari-তে আগে থেকে install করা app online অবস্থায় খুলুন/আবার সামনে আনুন। Browser update খুঁজে পেলে নতুন worker প্রস্তুত হওয়ার পর app-এর ভেতরে update banner দেখাতে পারে।
7. **“এখন আপডেট”** চাপলে app reload হয়ে নতুন version চালু হবে। **“পরে”** চাপলে সেই tab/session-এ banner লুকানো থাকবে; অন্য নতুন app session-এ আবার update prompt দেখা যেতে পারে।

## ব্যবহারকারীদের কী জানাবেন

- সাধারণ PHP/content পরিবর্তনে update banner না-ও আসতে পারে; নতুন পেজ খুললেই পরিবর্তন দেখা উচিত।
- CSS/JavaScript/app asset-এর release-এ banner এলে **“এখন আপডেট”** চাপতে বলুন।
- App-টি uninstall/reinstall করার দরকার নেই।
- App বন্ধ থাকলে সঙ্গে সঙ্গে ফোনে notification আসবে না। এটি background push notification ব্যবস্থা নয়।
- Browser-এর update check timing ও install behavior Chrome, Safari, Edge, Firefox এবং OS অনুযায়ী কিছুটা আলাদা হতে পারে।

## Release checklist

- [ ] পরিবর্তিত সব ফাইল live hosting-এ deploy হয়েছে।
- [ ] Page-এর CSS/JS URL version এবং Service Worker allowlist-এর URL মিলে।
- [ ] Cached asset বা Worker পরিবর্তন হলে `CACHE_VERSION` বাড়ানো হয়েছে।
- [ ] নতুন asset URL live-এ খুলে ঠিক ফাইল ফেরত দেয়।
- [ ] HTTPS site-এ install করা app-এ online update check করা হয়েছে।
- [ ] Update banner এলে “এখন আপডেট” ও “পরে”—দুটির আচরণ যাচাই হয়েছে।

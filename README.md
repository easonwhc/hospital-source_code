# 🏥 Hospital Medical Record System

本專案是一套完整的醫院病歷管理系統，支援病人、醫生、護理師、藥師等角色操作。  
後端採用 **MySQL + MongoDB** 混合資料庫架構，適合作為課堂專案與全端開發練習。

---

## 📌 系統需求

## 📌 系統需求

| 元件 | 版本需求 |
|------|----------|
| PHP | v8.1.25（XAMPP 內建） |
| MariaDB（相容 MySQL） | v10.4.32（相容 MySQL 5.7） |
| MongoDB Community Server | v8.2.0 |
| Composer | v2.7.7 |
| MongoDB PHP Driver | v2.1.0（已安裝，支援 libmongoc/libbson 1.30.4） |

---

# 📁 專案結構

hospital/
│── db/
│── doctor/
│── nurse/
│── patient/
│── pharmacist/
│── image/
│── vendor/ # Composer 會自動生成
│── composer.json
│── composer.lock
│── mongo_data/ # ⚠️ MongoDB 資料庫檔案，不要上傳 GitHub

---

# 🧰 安裝步驟

## ⭐ 1. 安裝 MongoDB Community Server

官方下載：  
https://www.mongodb.com/try/download/community

下載 Windows MSI 版本並完成安裝。

---

## ⭐ 2. 安裝 MongoDB Shell（mongosh）

https://www.mongodb.com/try/download/shell

---

## ⭐ 3. 安裝 MongoDB Compass（GUI 管理工具）

https://www.mongodb.com/products/tools/compass

Compass 可用於查看 collections 與資料。

---

## ⭐ 4. 設定 MongoDB 資料存放目錄（dbpath）

在本專案內建立資料夾：

hospital/mongo_data/

⚠️ 注意：  
此資料夾是 **MongoDB 實體資料檔**，  
---

## ⭐ 5. 安裝 MongoDB PHP Driver

本專案已提供 `php_mongodb.dll`  
（來源：php_mongodb.zip）

### 安裝方式：

1. 將 `php_mongodb.dll` 放到：

C:/xampp/php/ext/

markdown
複製程式碼

2. 編輯 php.ini：

C:/xampp/php/php.ini

複製程式碼

搜尋：

extension=

複製程式碼

在最底後面加入：

extension=mongodb


3. 重啟 Apache

---

## ⭐ 6. 啟動 MongoDB 伺服器（mongod 指令）

請在 CMD 執行：

```cmd
cd "C:/Program Files/MongoDB/Server/8.2/bin"
啟動：

cmd
複製程式碼
mongod.exe --dbpath "C:/xampp/htdocs/hospital/mongo_data"
成功會看到：

nginx
複製程式碼
waiting for connections on port 27017
⭐ 7. 用 Compass 連線 MongoDB
開啟 Compass → 填入：

arduino
複製程式碼
mongodb://localhost:27017
按 Connect 即可。

📦 安裝 vendor（Composer 套件）
本專案已提供 composer.json 和 composer.lock，
請在專案根目錄執行：

cmd
複製程式碼
composer install
成功後會自動生成：

複製程式碼
vendor/
⚠️ 注意：
vendor/ 不需要上傳 GitHub（會自動生成）。

🚀 專案啟動方式
啟動 XAMPP 的 Apache + MySQL

手動執行 mongod（啟動 MongoDB）

在瀏覽器開啟：

arduino
複製程式碼
http://localhost/hospital/
即可使用系統。

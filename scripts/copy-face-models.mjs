// Chép mô hình nhận diện của Human (@vladmandic/human) vào public/models/human.
// Trình duyệt (POS, trang Khách hàng) và worker camera cửa dùng chung thư mục này.
// Chạy một lần ở thư mục gốc dự án sau khi `npm i @vladmandic/human`:  node scripts/copy-face-models.mjs
import fs from 'node:fs'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..')
const source = path.join(root, 'node_modules', '@vladmandic', 'human', 'models')
const target = path.join(root, 'public', 'models', 'human')

// Chỉ chép các mô hình cần dùng: dò mặt (blazeface), điểm mốc (facemesh), vector nhận diện (faceres).
const wanted = ['blazeface', 'facemesh', 'faceres']

if (!fs.existsSync(source)) {
    console.error(`Không thấy ${source}. Chạy "npm i @vladmandic/human" ở thư mục gốc dự án trước.`)
    process.exit(1)
}

fs.mkdirSync(target, { recursive: true })
const files = fs.readdirSync(source).filter(name => wanted.some(model => name === `${model}.json` || name === `${model}.bin` || name.startsWith(`${model}-`)))
for (const name of files) fs.copyFileSync(path.join(source, name), path.join(target, name))

const missing = wanted.filter(model => !fs.existsSync(path.join(target, `${model}.json`)))
if (missing.length) {
    console.error(`Thiếu mô hình: ${missing.join(', ')}. Kiểm tra thư mục ${source}.`)
    process.exit(1)
}
console.log(`Đã chép ${files.length} file mô hình vào ${target}`)

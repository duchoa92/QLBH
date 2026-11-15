// Chép mô hình nhận diện khuôn mặt từ node_modules vào public/models/face (chạy một lần sau khi cài thư viện).
//   npm i @vladmandic/face-api
//   node scripts/copy-face-models.mjs
import { cpSync, existsSync, mkdirSync, readdirSync } from 'node:fs'
import { join } from 'node:path'

const source = join('node_modules', '@vladmandic', 'face-api', 'model')
const target = join('public', 'models', 'face')
const wanted = ['tiny_face_detector_model', 'ssd_mobilenetv1_model', 'face_landmark_68_tiny_model', 'face_recognition_model']

if (!existsSync(source)) {
    console.error('Chưa cài thư viện. Chạy: npm i @vladmandic/face-api')
    process.exit(1)
}

mkdirSync(target, { recursive: true })
const files = readdirSync(source).filter(name => wanted.some(prefix => name.startsWith(prefix)))
for (const name of files) cpSync(join(source, name), join(target, name))
console.log(`Đã chép ${files.length} file mô hình vào ${target}`)

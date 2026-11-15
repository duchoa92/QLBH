/**
 * Nhận diện khuôn mặt chạy ngay trên trình duyệt (face-api). Chỉ trả về vector đặc trưng 128 số,
 * không có ảnh nào được gửi lên máy chủ. Thư viện và mô hình chỉ được tải khi thật sự dùng tới.
 *
 * Cần: `npm i @vladmandic/face-api` và chạy `node scripts/copy-face-models.mjs` một lần
 * (chép mô hình vào public/models/face).
 */
const MODEL_URL = '/models/face'
let faceApiPromise = null
let stillModelPromise = null

export const loadFaceApi = () => {
    if (!faceApiPromise) {
        faceApiPromise = (async () => {
            const faceapi = await import('@vladmandic/face-api/dist/face-api.esm.js')
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL),
                faceapi.nets.faceLandmark68TinyNet.loadFromUri(MODEL_URL),
                faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL),
            ])
            return faceapi
        })().catch((error) => {
            faceApiPromise = null
            throw error
        })
    }
    return faceApiPromise
}

/**
 * Nhận diện 1 khuôn mặt trong khung hình video.
 * status: none (không có mặt) | multiple (nhiều người) | small (đứng xa quá) | ok (kèm descriptor)
 */
export const detectFace = async (video, { minFaceRatio = 0.2 } = {}) => {
    const faceapi = await loadFaceApi()
    const options = new faceapi.TinyFaceDetectorOptions({ inputSize: 320, scoreThreshold: 0.6 })
    const results = await faceapi.detectAllFaces(video, options).withFaceLandmarks(true).withFaceDescriptors()

    if (results.length === 0) return { status: 'none' }
    if (results.length > 1) return { status: 'multiple' }

    const face = results[0]
    // video dùng videoWidth, ảnh/canvas dùng naturalWidth/width.
    const mediaWidth = video.videoWidth || video.naturalWidth || video.width || 1
    const ratio = face.detection.box.width / mediaWidth
    if (ratio < minFaceRatio) return { status: 'small', ratio }

    return { status: 'ok', ratio, descriptor: Array.from(face.descriptor, value => Math.round(value * 1e5) / 1e5) }
}

/**
 * Nhận diện NHIỀU khuôn mặt trong khung hình video (nhiều khách cùng đứng trước camera).
 * Trả { faces, tooSmall }: faces đã sắp theo kích thước giảm dần (người gần camera nhất đứng đầu), mỗi mặt gồm
 * box, ratio, sharpness (độ nét, càng cao càng nét) và descriptor; tooSmall là số mặt bị bỏ vì quá nhỏ/quá xa.
 */
export const detectFaces = async (video, { maxFaces = 6 } = {}) => {
    const faceapi = await loadFaceApi()
    // inputSize 416 + ngưỡng thấp hơn: dò được cả mặt nhỏ hoặc hơi mờ; các mặt kém chất lượng vẫn bị loại ở bước kích thước.
    const options = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.45 })
    const results = await faceapi.detectAllFaces(video, options).withFaceLandmarks(true).withFaceDescriptors()

    const mediaWidth = video.videoWidth || video.naturalWidth || video.width || 640
    const minWidth = Math.max(56, mediaWidth * 0.07)
    const faces = []
    let tooSmall = 0

    for (const result of results) {
        const box = result.detection.box
        if (box.width < minWidth) { tooSmall += 1; continue }
        faces.push({
            box: { x: box.x, y: box.y, width: box.width, height: box.height },
            ratio: box.width / mediaWidth,
            sharpness: faceSharpness(video, box),
            descriptor: Array.from(result.descriptor, value => Math.round(value * 1e5) / 1e5),
        })
    }

    faces.sort((a, b) => b.box.width * b.box.height - a.box.width * a.box.height)

    return { faces: faces.slice(0, maxFaces), tooSmall }
}

const sharpnessCanvas = typeof document === 'undefined' ? null : document.createElement('canvas')

/**
 * Độ nét của vùng mặt (phương sai Laplacian trên ảnh xám thu về 64x64). Ảnh mờ/rung cho giá trị thấp.
 * Dùng để chọn khung hình nét nhất khi nhận diện, không dùng để từ chối khách.
 */
export const faceSharpness = (source, box, size = 64) => {
    if (!sharpnessCanvas) return 0
    const sourceWidth = source.videoWidth || source.naturalWidth || source.width
    const sourceHeight = source.videoHeight || source.naturalHeight || source.height
    const x = Math.max(0, Math.min(box.x, sourceWidth - 1))
    const y = Math.max(0, Math.min(box.y, sourceHeight - 1))
    const width = Math.max(1, Math.min(box.width, sourceWidth - x))
    const height = Math.max(1, Math.min(box.height, sourceHeight - y))

    sharpnessCanvas.width = size
    sharpnessCanvas.height = size
    const context = sharpnessCanvas.getContext('2d', { willReadFrequently: true })
    context.drawImage(source, x, y, width, height, 0, 0, size, size)
    const { data } = context.getImageData(0, 0, size, size)

    const gray = new Float32Array(size * size)
    for (let i = 0; i < gray.length; i += 1) {
        const p = i * 4
        gray[i] = 0.299 * data[p] + 0.587 * data[p + 1] + 0.114 * data[p + 2]
    }

    let sum = 0
    let sumSquares = 0
    let count = 0
    for (let row = 1; row < size - 1; row += 1) {
        for (let column = 1; column < size - 1; column += 1) {
            const i = row * size + column
            const laplacian = gray[i - 1] + gray[i + 1] + gray[i - size] + gray[i + size] - 4 * gray[i]
            sum += laplacian
            sumSquares += laplacian * laplacian
            count += 1
        }
    }
    const mean = sum / count

    return sumSquares / count - mean * mean
}

/** Ảnh nhỏ của một khuôn mặt (có chừa viền) để nhân viên biết gợi ý ứng với người nào. Chỉ giữ trong bộ nhớ, không lưu. */
export const cropThumbnail = (source, box, size = 72) => {
    const sourceWidth = source.videoWidth || source.naturalWidth || source.width
    const sourceHeight = source.videoHeight || source.naturalHeight || source.height
    const pad = Math.max(box.width, box.height) * 0.25
    const x = Math.max(0, box.x - pad)
    const y = Math.max(0, box.y - pad)
    const width = Math.min(sourceWidth - x, box.width + pad * 2)
    const height = Math.min(sourceHeight - y, box.height + pad * 2)
    const side = Math.min(width, height)

    const canvas = document.createElement('canvas')
    canvas.width = size
    canvas.height = size
    canvas.getContext('2d').drawImage(source, x + (width - side) / 2, y + (height - side) / 2, side, side, 0, 0, size, size)

    return canvas.toDataURL('image/jpeg', 0.7)
}

export const startCamera = async (video, facingMode = 'user') => {
    if (!navigator.mediaDevices?.getUserMedia) {
        throw new Error('Trình duyệt không cho dùng camera. Camera chỉ hoạt động khi mở bằng HTTPS hoặc localhost.')
    }
    const stream = await navigator.mediaDevices.getUserMedia({
        // 1280x720: mặt ở xa hoặc nhiều người cùng đứng trước camera vẫn đủ điểm ảnh để nhận ra (mô hình tự thu nhỏ khi dò).
        video: { facingMode: { ideal: facingMode }, width: { ideal: 1280 }, height: { ideal: 720 }, frameRate: { ideal: 30 } },
        audio: false,
    })
    video.srcObject = stream
    await video.play()
    return stream
}

export const stopCamera = (stream, video) => {
    stream?.getTracks().forEach(track => track.stop())
    if (video) video.srcObject = null
}

export const cameraErrorMessage = (error) => {
    if (error?.name === 'NotAllowedError') return 'Chưa được cấp quyền camera. Hãy cho phép camera cho trang này.'
    if (error?.name === 'NotFoundError') return 'Không tìm thấy camera.'
    if (/Failed to fetch|404|model/i.test(error?.message || '')) return 'Chưa có mô hình nhận diện. Chạy: node scripts/copy-face-models.mjs'
    return error?.message || 'Không mở được camera.'
}

/**
 * Đọc ảnh (file hoặc blob) thành canvas đã thu nhỏ, tự xoay đúng chiều theo EXIF (ảnh chụp bằng điện thoại).
 * Ảnh gốc không được lưu hay gửi đi; chỉ canvas này được dùng để tính vector.
 */
export const blobToCanvas = async (blob, maxSize = 1280) => {
    const bitmap = await createImageBitmap(blob, { imageOrientation: 'from-image' })
    const scale = Math.min(1, maxSize / Math.max(bitmap.width, bitmap.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(bitmap.width * scale)
    canvas.height = Math.round(bitmap.height * scale)
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height)
    bitmap.close?.()
    return canvas
}

/** Ảnh nhỏ (chỉ để hiển thị trong màn hình đăng ký, không lưu) từ video hoặc canvas. */
export const makeThumbnail = (source, width = 96) => {
    const sourceWidth = source.videoWidth || source.width
    const sourceHeight = source.videoHeight || source.height
    const canvas = document.createElement('canvas')
    canvas.width = width
    canvas.height = Math.round(width * sourceHeight / sourceWidth)
    canvas.getContext('2d').drawImage(source, 0, 0, canvas.width, canvas.height)
    return canvas.toDataURL('image/jpeg', 0.7)
}

export const faceStatusMessage = {
    none: 'Không thấy khuôn mặt trong ảnh.',
    multiple: 'Ảnh có nhiều khuôn mặt. Hãy dùng ảnh chỉ có một người (hoặc cắt ảnh).',
    small: 'Khuôn mặt quá nhỏ hoặc quá xa. Hãy dùng ảnh gần hơn hoặc cắt sát mặt.',
}

/* ------------------------------------------------------------------
 * Ảnh tĩnh (ảnh tải lên, ảnh camera an ninh): dò mặt kỹ hơn để nhận ra cả khi ảnh nhỏ/mờ
 * ------------------------------------------------------------------ */
const loadStillModel = async () => {
    const faceapi = await loadFaceApi()
    if (!stillModelPromise) {
        // SSD MobileNet chính xác hơn bộ dò nhanh dùng ở camera trực tiếp, nhưng chậm hơn nên chỉ dùng cho ảnh tĩnh.
        stillModelPromise = faceapi.nets.ssdMobilenetv1.loadFromUri(MODEL_URL).catch((error) => {
            stillModelPromise = null
            throw error
        })
    }
    await stillModelPromise
    return faceapi
}

const scaleCanvas = (source, factor) => {
    const maxSide = 3200
    const k = Math.min(factor, maxSide / Math.max(source.width, source.height))
    const canvas = document.createElement('canvas')
    canvas.width = Math.round(source.width * k)
    canvas.height = Math.round(source.height * k)
    const context = canvas.getContext('2d')
    context.imageSmoothingEnabled = true
    context.imageSmoothingQuality = 'high'
    context.drawImage(source, 0, 0, canvas.width, canvas.height)
    return canvas
}

const mirrorCanvas = (source) => {
    const canvas = document.createElement('canvas')
    canvas.width = source.width
    canvas.height = source.height
    const context = canvas.getContext('2d')
    context.translate(canvas.width, 0)
    context.scale(-1, 1)
    context.drawImage(source, 0, 0)
    return canvas
}

/**
 * Nhận diện 1 khuôn mặt trong ảnh tĩnh (canvas). Mặt nhỏ sẽ được phóng to rồi nhận diện lại để mô hình có đủ chi tiết.
 * Trả thêm descriptorFlip (từ bản lật gương) để 1 ảnh vẫn cho 2 mẫu, giúp nhận ra khách ở cả hai bên mặt.
 */
export const detectStill = async (canvas) => {
    const faceapi = await loadStillModel()
    const options = new faceapi.SsdMobilenetv1Options({ minConfidence: 0.4 })
    const detect = target => faceapi.detectAllFaces(target, options).withFaceLandmarks(true).withFaceDescriptors()

    let work = canvas
    let results = await detect(work)
    if (results.length === 0) {
        work = scaleCanvas(canvas, 2)
        results = await detect(work)
    }
    if (results.length === 0) return { status: 'none' }
    if (results.length > 1) return { status: 'multiple' }

    let face = results[0]
    const originalRatio = face.detection.box.width / work.width

    // Mặt nhỏ (dưới ~120px): phóng to để mặt rộng ~160px rồi nhận diện lại.
    const faceWidth = face.detection.box.width
    if (faceWidth < 120) {
        const enlarged = scaleCanvas(work, Math.min(4, 160 / faceWidth))
        const again = await detect(enlarged)
        if (again.length === 1) {
            work = enlarged
            face = again[0]
        }
    }

    const round = descriptor => Array.from(descriptor, value => Math.round(value * 1e5) / 1e5)
    const flipped = await detect(mirrorCanvas(work))

    return {
        status: 'ok',
        ratio: originalRatio,
        descriptor: round(face.descriptor),
        descriptorFlip: flipped.length === 1 ? round(flipped[0].descriptor) : null,
    }
}

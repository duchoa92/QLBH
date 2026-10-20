let audioContext = null

const findVoice = (language) => {
    if (typeof window === 'undefined' || !window.speechSynthesis) return null
    const languagePrefix = language.split('-')[0].toLowerCase()
    const voices = window.speechSynthesis.getVoices()
    const voiceLanguage = item => String(item.lang || '').toLowerCase()
    return voices.find(item => voiceLanguage(item) === language.toLowerCase())
        || voices.find(item => voiceLanguage(item).startsWith(`${languagePrefix}-`))
        || voices.find(item => voiceLanguage(item) === languagePrefix)
        || null
}

export function hasPaymentVoice(language = 'vi-VN') {
    return Boolean(findVoice(language))
}

export function testPaymentVoice(language = 'vi-VN') {
    const voice = findVoice(language)
    if (!voice || typeof window === 'undefined' || !window.speechSynthesis) return false

    try {
        window.speechSynthesis.cancel()
        const utterance = new SpeechSynthesisUtterance(language === 'en-US'
            ? 'Payment notification voice is working.'
            : 'Giọng đọc thông báo thanh toán đang hoạt động.')
        utterance.voice = voice
        utterance.lang = voice.lang
        utterance.rate = 1
        utterance.volume = 1
        window.speechSynthesis.speak(utterance)
        return true
    } catch (error) {
        console.warn('Không thể thử giọng đọc thanh toán', error)
        return false
    }
}

export function armBankPaymentAudio() {
    if (typeof window === 'undefined') return

    try {
        const AudioContextClass = window.AudioContext || window.webkitAudioContext
        if (AudioContextClass && !audioContext) audioContext = new AudioContextClass()
        if (audioContext?.state === 'suspended') audioContext.resume().catch(() => {})
        window.speechSynthesis?.getVoices()
    } catch (error) {
        console.warn('Không thể bật âm báo thanh toán', error)
    }
}

const playTonePattern = (frequencies) => {
    if (!audioContext) return

    try {
        if (audioContext.state === 'suspended') audioContext.resume().catch(() => {})
        const startAt = audioContext.currentTime + 0.03
        frequencies.forEach((frequency, index) => {
            const start = startAt + index * 0.2
            const oscillator = audioContext.createOscillator()
            const gain = audioContext.createGain()
            oscillator.type = 'sine'
            oscillator.frequency.value = frequency
            gain.gain.setValueAtTime(0.0001, start)
            gain.gain.exponentialRampToValueAtTime(0.16, start + 0.025)
            gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.15)
            oscillator.connect(gain)
            gain.connect(audioContext.destination)
            oscillator.start(start)
            oscillator.stop(start + 0.16)
        })
    } catch (error) {
        console.warn('Không thể phát âm báo thanh toán', error)
    }
}

export function announceBankPayment({ kind, amount = 0, language = 'vi-VN', speak = true }) {
    const formattedAmount = new Intl.NumberFormat(language).format(Number(amount || 0))
    const messages = language === 'en-US'
        ? {
            partial: `Payment is short. ${formattedAmount} dong remaining.`,
            exact: 'Payment received in full.',
            excess: `Payment exceeds the amount by ${formattedAmount} dong. The invoice is complete.`,
        }
        : {
            partial: `Đã nhận thiếu, còn thiếu ${formattedAmount} đồng.`,
            exact: 'Đã nhận đủ tiền chuyển khoản.',
            excess: `Khách chuyển thừa ${formattedAmount} đồng. Hóa đơn đã hoàn tất.`,
        }
    const patterns = {
        partial: [440, 392],
        exact: [660, 880],
        excess: [660, 880, 1040],
    }

    playTonePattern(patterns[kind] || patterns.exact)

    if (speak && typeof window !== 'undefined' && window.speechSynthesis && messages[kind]) {
        try {
            const voice = findVoice(language)
            // Do not silently use a voice in another language: keep the tone
            // alert, and wait for the user to install/select a matching voice.
            if (!voice) return
            window.speechSynthesis.cancel()
            const utterance = new SpeechSynthesisUtterance(messages[kind])
            utterance.voice = voice
            utterance.lang = voice.lang
            utterance.rate = 1
            utterance.volume = 1
            window.speechSynthesis.speak(utterance)
        } catch (error) {
            console.warn('Không thể đọc thông báo thanh toán', error)
        }
    }
}

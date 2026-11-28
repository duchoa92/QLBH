<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    environment: { type: Object, default: () => ({ checks: [], drivers: {}, cron: {}, ready: true, os: '' }) },
    prepareResults: { type: Array, default: null },
    savedDatabase: { type: Object, default: null },
    certificateUrl: { type: String, default: null },
    done: { type: Object, default: null },
});

const copied = ref(false);
const shopError = computed(() => Object.entries(form.errors).find(([key]) => /^(shop_|app_|date_format|currency_)/.test(key))?.[1] ?? '');
const cronInstalled = computed(() => props.environment.cron?.installed === true);
const loginUrl = computed(() => `${window.location.origin}/login`);
function shopLines(shop) {
    if (!shop) return [];
    return ['', '[Cửa hàng và hệ thống]', `Tên cửa hàng: ${shop.name}`,
        shop.phone ? `Điện thoại: ${shop.phone}` : null, shop.address ? `Địa chỉ: ${shop.address}` : null,
        `Ngôn ngữ: ${localeLabels[shop.locale]}`, `Múi giờ: ${timezoneLabels[shop.timezone]}`,
        `Định dạng ngày: ${formatDate(shop.date_format)}`, `Tiền tệ: ${shop.currency}`].filter((line) => line !== null);
}
function systemLines(system) {
    if (!system) return [];
    const lines = ['', '[Chạy nền và tự khởi động]'];
    if (system.linux) {
        lines.push(`Lịch chạy nền (cron): ${system.cron === true ? 'Đã bật' : system.cron === false ? 'CHƯA bật (sao lưu tự động, nhắc việc sẽ không chạy)' : 'Không kiểm tra được'}`);
        for (const [name, enabled] of Object.entries(system.autostart || {})) {
            if (enabled !== null) lines.push(`Tự chạy khi bật máy - ${name}: ${enabled ? 'Có' : 'KHÔNG'}`);
        }
    }
    if (system.disk && system.disk.used_percent !== null) {
        lines.push(`Ổ đĩa: đã dùng ${system.disk.used_percent}%, còn trống ${system.disk.free_gb} GB / ${system.disk.total_gb} GB`);
    }
    return lines;
}
const doneText = computed(() => {
    const d = props.done;
    if (!d) return '';
    const db = d.database || {};
    const lines = [
        'THÔNG TIN QLBH', '',
        '[Đăng nhập website]',
        `Địa chỉ: ${loginUrl.value}`,
        `Tên đăng nhập: ${d.username}`,
        `Mật khẩu: ${d.password}`,
        d.name ? `Họ và tên: ${d.name}` : null,
        d.phone ? `Điện thoại: ${d.phone}` : null,
        d.email ? `Email: ${d.email}` : null,
        '', '[Cơ sở dữ liệu]',
        `Loại: ${db.driver === 'sqlite' ? 'SQLite' : 'MySQL / MariaDB'}`,
        ...(db.file
            ? [`Tệp dữ liệu: ${db.file}`]
            : [`Máy chủ: ${db.host}:${db.port}`, `Tên database: ${db.name}`, `Tài khoản: ${db.username}`, `Mật khẩu: ${db.password}`]),
        ...shopLines(d.shop),
        ...systemLines(d.system),
    ];
    return lines.filter((line) => line !== null).join('\n');
});
async function copyInfo() {
    try {
        await navigator.clipboard.writeText(doneText.value);
    } catch {
        const area = document.createElement('textarea');
        area.value = doneText.value;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        area.remove();
    }
    copied.value = true;
    setTimeout(() => { copied.value = false; }, 2500);
}
function downloadInfo() {
    const blob = new Blob(['\ufeff' + doneText.value + '\n'], { type: 'text/plain;charset=utf-8' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'qlbh-thong-tin-cai-dat.txt';
    link.click();
    URL.revokeObjectURL(link.href);
}

const step = ref(0);
const status = ref('');
const showPassword = ref(false); // dùng chung cho cả 2 ô mật khẩu quản trị
const showDatabasePassword = ref(false);
const showEnvironment = ref(false);
const preparing = ref(false);
const page = usePage();
const form = useForm({
    database_driver: 'mysql', database_host: '127.0.0.1', database_port: '3306',
    database_name: props.savedDatabase ? 'qlbh' : '', database_username: props.savedDatabase?.username ?? 'root', database_password: '',
    admin_username: 'admin', admin_name: '', admin_phone: '', admin_email: '',
    admin_password: '', admin_password_confirmation: '',
    shop_name: '', shop_phone: '', shop_address: '',
    app_locale: 'vi', app_timezone: 'Asia/Ho_Chi_Minh', date_format: 'd/m/Y', currency_symbol: '₫', currency_format: 'vi-VN',
    install_cron: props.environment.os === 'Linux' && props.environment.cron?.installed !== true,
});
const steps = ['Database', 'Quản trị', 'Cửa hàng', 'Xác nhận'];
const LAST_STEP = 3;
const localeLabels = { vi: 'Tiếng Việt', en: 'English' };
const timezoneLabels = { 'Asia/Ho_Chi_Minh': 'Việt Nam (GMT+7)', 'Asia/Bangkok': 'Bangkok (GMT+7)', UTC: 'UTC (GMT+0)' };
const dateFormats = { 'd/m/Y': 'Ngày/tháng/năm', 'Y-m-d': 'Năm-tháng-ngày', 'm/d/Y': 'Tháng/ngày/năm' };
const currencyFormats = { 'vi-VN': 'Kiểu Việt Nam (1.234.567)', 'en-US': 'Kiểu quốc tế (1,234,567)' };
function formatDate(pattern) {
    const now = new Date();
    const d = String(now.getDate()).padStart(2, '0');
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const y = now.getFullYear();
    return pattern.replace('d', d).replace('m', m).replace('Y', y);
}
const errors = computed(() => page.props.errors ?? {});
const isLinux = computed(() => props.environment.os === 'Linux');
const failedChecks = computed(() => (props.environment.checks ?? []).filter((item) => !item.ok));
const blockingChecks = computed(() => failedChecks.value.filter((item) => item.level === 'error'));
const savedHint = computed(() => props.savedDatabase?.has_password && form.database_driver === 'mysql' && form.database_username === props.savedDatabase.username && !form.database_password);
const driverMissing = computed(() => props.environment.drivers?.[form.database_driver] === false);

function prepareServer() {
    preparing.value = true;
    router.post('/setup/prepare', { install_cron: false }, {
        preserveScroll: true,
        preserveState: true,
        only: ['environment', 'prepareResults'],
        onFinish: () => { preparing.value = false; showEnvironment.value = true; },
    });
}

function next() {
    status.value = '';
    if (step.value === 0) {
        const required = form.database_driver === 'sqlite' ? ['database_name'] : ['database_host', 'database_name', 'database_username'];
        const missing = required.find((field) => !String(form[field] ?? '').trim());
        if (missing) {
            form.setError(missing, 'Bắt buộc');
            return;
        }
        if (driverMissing.value) {
            form.setError('database_driver', `PHP chưa bật pdo_${form.database_driver}`);
            showEnvironment.value = true;
            return;
        }
    }
    if (step.value === 1) {
        const missing = ['admin_username', 'admin_name', 'admin_phone', 'admin_email', 'admin_password', 'admin_password_confirmation'].find((field) => !String(form[field] ?? '').trim());
        if (missing) {
            form.setError(missing, 'Bắt buộc');
            return;
        }
        if (form.admin_password.length < 8) {
            form.setError('admin_password', 'Tối thiểu 8 ký tự');
            return;
        }
        if (form.admin_password !== form.admin_password_confirmation) {
            form.setError('admin_password_confirmation', 'Mật khẩu chưa khớp');
            return;
        }
    }
    if (step.value === 2) {
        const missing = ['shop_name', 'currency_symbol'].find((field) => !String(form[field] ?? '').trim());
        if (missing) {
            form.setError(missing, 'Bắt buộc');
            return;
        }
    }
    form.clearErrors();
    step.value = Math.min(step.value + 1, LAST_STEP);
}
function back() { status.value = ''; step.value = Math.max(0, step.value - 1); }
function testConnection() {
    status.value = '';
    form.post('/setup/test', {
        preserveScroll: true,
        onSuccess: () => { status.value = page.props.flash?.status ?? 'Kết nối thành công.'; },
    });
}
function install() {
    form.post('/setup/install', {
        preserveScroll: true,
        onSuccess: () => form.reset('admin_password', 'admin_password_confirmation', 'database_password'),
        // Lỗi ở bước nào thì quay về đúng bước đó để sửa.
        onError: (formErrors) => {
            const keys = Object.keys(formErrors);
            if (keys.some((key) => key.startsWith('database'))) step.value = 0;
            else if (keys.some((key) => key.startsWith('admin'))) step.value = 1;
        },
    });
}
function changeDriver() {
    form.database_port = form.database_driver === 'pgsql' ? '5432' : form.database_driver === 'mysql' ? '3306' : '';
    form.clearErrors();
}
function goLogin() {
    window.location.href = route('login');
}
</script>

<template>
    <Head title="Thiết lập" />
    <div class="shell">
        <header class="brand"><span class="mark">N</span><span>Thiết lập</span></header>

        <main class="card">
            <!-- Hoàn tất -->
            <section v-if="done" class="done" role="status">
                <div class="done-icon">✓</div>
                <h1>Thiết lập thành công</h1>
                <p class="save-warn">⚠ Hãy lưu lại tên đăng nhập và mật khẩu bên dưới: bấm <b>Tải file</b> hoặc <b>Sao chép</b>. Thông tin sẽ biến mất sau khi bạn đăng nhập.</p>

                <div class="cred" aria-label="Tài khoản đăng nhập">
                    <div><span>Tên đăng nhập</span><strong>{{ done.username }}</strong></div>
                    <div><span>Mật khẩu</span><strong>{{ done.password }}</strong></div>
                </div>

                <div class="info">
                    <h2>Đăng nhập website</h2>
                    <dl>
                        <div><dt>Địa chỉ</dt><dd>{{ loginUrl }}</dd></div>
                        <div v-if="done.name"><dt>Họ và tên</dt><dd>{{ done.name }}</dd></div>
                        <div v-if="done.phone"><dt>Điện thoại</dt><dd>{{ done.phone }}</dd></div>
                        <div v-if="done.email"><dt>Email</dt><dd>{{ done.email }}</dd></div>
                    </dl>
                    <template v-if="done.shop">
                        <h2>Cửa hàng và hệ thống</h2>
                        <dl>
                            <div><dt>Tên cửa hàng</dt><dd>{{ done.shop.name }}</dd></div>
                            <div v-if="done.shop.phone"><dt>Điện thoại</dt><dd>{{ done.shop.phone }}</dd></div>
                            <div v-if="done.shop.address"><dt>Địa chỉ</dt><dd>{{ done.shop.address }}</dd></div>
                            <div><dt>Ngôn ngữ</dt><dd>{{ localeLabels[done.shop.locale] }}</dd></div>
                            <div><dt>Múi giờ</dt><dd>{{ timezoneLabels[done.shop.timezone] }}</dd></div>
                            <div><dt>Định dạng ngày</dt><dd>{{ formatDate(done.shop.date_format) }}</dd></div>
                            <div><dt>Tiền tệ</dt><dd>{{ done.shop.currency }} · {{ currencyFormats[done.shop.currency_format] }}</dd></div>
                        </dl>
                    </template>
                    <h2>Cơ sở dữ liệu</h2>
                    <dl v-if="done.database">
                        <div><dt>Loại</dt><dd>{{ done.database.driver === 'sqlite' ? 'SQLite' : 'MySQL / MariaDB' }}</dd></div>
                        <div v-if="done.database.file"><dt>Tệp dữ liệu</dt><dd>{{ done.database.file }}</dd></div>
                        <template v-else>
                            <div><dt>Máy chủ</dt><dd>{{ done.database.host }}:{{ done.database.port }}</dd></div>
                            <div><dt>Tên database</dt><dd>{{ done.database.name }}</dd></div>
                            <div><dt>Tài khoản</dt><dd>{{ done.database.username }}</dd></div>
                            <div><dt>Mật khẩu</dt><dd>{{ done.database.password }}</dd></div>
                        </template>
                    </dl>
                </div>

                <div v-if="done.system" class="info">
                    <h2>Chạy nền và tự khởi động</h2>
                    <dl>
                        <template v-if="done.system.linux">
                            <div><dt>Lịch chạy nền</dt><dd :class="done.system.cron === true ? 'good' : 'warn'">{{ done.system.cron === true ? '✓ Đã bật (sao lưu tự động, nhắc việc, dọn dữ liệu cũ)' : done.system.cron === false ? '✗ Chưa bật: sao lưu tự động và nhắc việc sẽ KHÔNG chạy' : 'Không kiểm tra được' }}</dd></div>
                            <template v-for="(enabled, name) in done.system.autostart" :key="name">
                                <div v-if="enabled !== null"><dt>{{ name }}</dt><dd :class="enabled ? 'good' : 'warn'">{{ enabled ? '✓ Tự chạy khi bật máy' : '✗ Không tự chạy khi bật máy' }}</dd></div>
                            </template>
                        </template>
                        <div v-if="done.system.disk && done.system.disk.used_percent !== null"><dt>Ổ đĩa</dt><dd :class="done.system.disk.level === 'ok' ? 'good' : 'warn'">Đã dùng {{ done.system.disk.used_percent }}% · còn trống {{ done.system.disk.free_gb }} GB / {{ done.system.disk.total_gb }} GB</dd></div>
                    </dl>
                    <p class="hint-line">Website sẽ tự cảnh báo trên màn hình quản trị khi ổ đĩa gần đầy hoặc có lỗi ổ đĩa (cần sao lưu ngay).</p>
                </div>

                <p v-if="done.notes" class="note">{{ done.notes }}</p>
                <div class="done-actions">
                    <button class="btn sm" type="button" @click="copyInfo">{{ copied ? 'Đã sao chép ✓' : 'Sao chép' }}</button>
                    <button class="btn sm" type="button" @click="downloadInfo">Tải file (.txt)</button>
                    <button class="btn primary big" type="button" @click="goLogin">Đăng nhập</button>
                </div>
            </section>

            <template v-else>
                <ol class="steps">
                    <li v-for="(label, index) in steps" :key="label" :class="{ active: step === index, done: step > index }">
                        <span class="num">{{ step > index ? '✓' : index + 1 }}</span><span class="label">{{ label }}</span>
                    </li>
                </ol>

                <!-- Bước 1: cơ sở dữ liệu -->
                <section v-if="step === 0">
                    <h1>Cơ sở dữ liệu</h1>

                    <!-- Chỉ hiện khi máy chủ có vấn đề -->
                    <div v-if="failedChecks.length" class="env" :class="{ bad: blockingChecks.length }">
                        <span class="env-icon">!</span>
                        <div class="env-text"><b>{{ blockingChecks.length ? 'Máy chủ chưa sẵn sàng' : 'Cần lưu ý' }}</b></div>
                        <button class="btn ghost" type="button" @click="showEnvironment = !showEnvironment">{{ showEnvironment ? 'Ẩn' : 'Chi tiết' }}</button>
                        <button class="btn primary sm" type="button" :disabled="preparing" @click="prepareServer">{{ preparing ? 'Đang cấu hình…' : 'Tự cấu hình' }}</button>
                    </div>
                    <ul v-if="failedChecks.length && showEnvironment" class="checks">
                        <li v-for="item in failedChecks" :key="item.key" :class="item.level">
                            <span>{{ item.level === 'error' ? '✕' : '!' }}</span>
                            <div>{{ item.label }}<small v-if="item.detail"> — {{ item.detail }}</small><code v-if="item.fix">{{ item.fix }}</code></div>
                        </li>
                    </ul>
                    <ul v-if="prepareResults?.length" class="checks">
                        <li v-for="result in prepareResults" :key="result.label" :class="result.ok ? 'ok' : 'error'"><span>{{ result.ok ? '✓' : '✕' }}</span><div>{{ result.label }}<small> — {{ result.message }}</small></div></li>
                    </ul>

                    <div class="grid">
                        <label>Loại
                            <select v-model="form.database_driver" @change="changeDriver"><option value="mysql">MySQL / MariaDB</option><option value="pgsql">PostgreSQL</option><option value="sqlite">SQLite</option></select>
                            <small v-if="driverMissing || form.errors.database_driver">{{ form.errors.database_driver || `PHP chưa bật pdo_${form.database_driver}` }}</small>
                        </label>
                        <label>Tên database
                            <input v-model="form.database_name" placeholder="ví dụ: qlbh"><small v-if="form.errors.database_name">{{ form.errors.database_name }}</small>
                        </label>
                        <template v-if="form.database_driver !== 'sqlite'">
                            <label>Máy chủ<input v-model="form.database_host" placeholder="ví dụ: 127.0.0.1"><small v-if="form.errors.database_host">{{ form.errors.database_host }}</small></label>
                            <label>Cổng<input v-model="form.database_port" inputmode="numeric" placeholder="ví dụ: 3306"></label>
                            <label>Tài khoản<input v-model="form.database_username" placeholder="ví dụ: root"><small v-if="form.errors.database_username">{{ form.errors.database_username }}</small></label>
                            <label>Mật khẩu
                                <span class="password"><input v-model="form.database_password" :type="showDatabasePassword ? 'text' : 'password'" :placeholder="savedHint ? '••••••••  (đã đặt sẵn)' : ''" autocomplete="off"><button type="button" class="eye" :title="showDatabasePassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" :aria-label="showDatabasePassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" @click="showDatabasePassword = !showDatabasePassword"><template v-if="showDatabasePassword"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.9 10.9 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg></template><template v-else><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></template></button></span>
                                <small v-if="savedHint" class="hint">Mật khẩu đã được đặt sẵn khi chạy script cài đặt. Để trống ô này để dùng mật khẩu đó.</small>
                            </label>
                        </template>
                    </div>
                    <p v-if="shopError" class="banner error">Thông tin cửa hàng chưa hợp lệ: {{ shopError }} (bấm <b>Sửa</b> ở dòng Cửa hàng).</p>
                    <p v-if="errors.database" class="banner error">{{ errors.database }}</p>
                    <p v-if="status" class="banner success">{{ status }}</p>
                    <footer class="actions"><button class="btn outline" type="button" :disabled="form.processing" @click="testConnection">Kiểm tra kết nối</button><button class="btn primary" type="button" @click="next">Tiếp tục</button></footer>
                </section>

                <!-- Bước 2: quản trị viên -->
                <section v-else-if="step === 1">
                    <h1>Quản trị viên</h1>
                    <div class="grid">
                        <label>Tên đăng nhập<input v-model="form.admin_username" autocomplete="username"><small v-if="form.errors.admin_username">{{ form.errors.admin_username }}</small></label>
                        <label>Họ và tên<input v-model="form.admin_name" placeholder="ví dụ: Đức Hòa" autocomplete="name"><small v-if="form.errors.admin_name">{{ form.errors.admin_name }}</small></label>
                        <label>Số điện thoại<input v-model="form.admin_phone" type="tel" placeholder="ví dụ: 0906064789" autocomplete="tel"><small v-if="form.errors.admin_phone">{{ form.errors.admin_phone }}</small></label>
                        <label>Email<input v-model="form.admin_email" type="email" placeholder="ví dụ: duchoa.92@gmail.com" autocomplete="email"><small v-if="form.errors.admin_email">{{ form.errors.admin_email }}</small></label>
                        <label>Mật khẩu
                            <span class="password"><input v-model="form.admin_password" :type="showPassword ? 'text' : 'password'" placeholder="Từ 8 ký tự" autocomplete="new-password"><button type="button" class="eye" :title="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" @click="showPassword = !showPassword"><template v-if="showPassword"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.9 10.9 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg></template><template v-else><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></template></button></span>
                            <small v-if="form.errors.admin_password">{{ form.errors.admin_password }}</small>
                        </label>
                        <label>Nhập lại mật khẩu
                            <span class="password"><input v-model="form.admin_password_confirmation" :type="showPassword ? 'text' : 'password'" autocomplete="new-password"><button type="button" class="eye" :title="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" :aria-label="showPassword ? 'Ẩn mật khẩu' : 'Hiện mật khẩu'" @click="showPassword = !showPassword"><template v-if="showPassword"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.9 10.9 0 0 1 12 19c-6.5 0-10-7-10-7a18.5 18.5 0 0 1 5.06-5.94M9.9 4.24A10.9 10.9 0 0 1 12 5c6.5 0 10 7 10 7a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="m2 2 20 20"/></svg></template><template v-else><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg></template></button></span>
                            <small v-if="form.errors.admin_password_confirmation">{{ form.errors.admin_password_confirmation }}</small>
                        </label>
                    </div>
                    <footer class="actions"><button class="btn outline" type="button" @click="back">Quay lại</button><button class="btn primary" type="button" @click="next">Tiếp tục</button></footer>
                </section>

                <!-- Bước 3: xác nhận -->
                <!-- Bước 3: cửa hàng và cài đặt hệ thống -->
                <section v-else-if="step === 2">
                    <h1>Cửa hàng và hệ thống</h1>
                    <p class="lead">Các thông tin này dùng cho hóa đơn, ngày giờ và tiền tệ. Sau này vẫn sửa được trong <b>Cài đặt</b>.</p>
                    <div class="grid">
                        <label style="grid-column:1/-1">Tên cửa hàng<input v-model="form.shop_name" placeholder="ví dụ: Đức Hòa Computer"><small v-if="form.errors.shop_name">{{ form.errors.shop_name }}</small></label>
                        <label>Số điện thoại cửa hàng<input v-model="form.shop_phone" type="tel" placeholder="ví dụ: 0906064789"></label>
                        <label>Địa chỉ<input v-model="form.shop_address" placeholder="ví dụ: Vạn Xuân, Hưng Yên"></label>
                        <label>Ngôn ngữ
                            <select v-model="form.app_locale"><option v-for="(label, value) in localeLabels" :key="value" :value="value">{{ label }}</option></select>
                        </label>
                        <label>Múi giờ
                            <select v-model="form.app_timezone"><option v-for="(label, value) in timezoneLabels" :key="value" :value="value">{{ label }}</option></select>
                        </label>
                        <label>Định dạng ngày
                            <select v-model="form.date_format"><option v-for="(label, value) in dateFormats" :key="value" :value="value">{{ label }} ({{ formatDate(value) }})</option></select>
                        </label>
                        <label>Định dạng số tiền
                            <select v-model="form.currency_format"><option v-for="(label, value) in currencyFormats" :key="value" :value="value">{{ label }}</option></select>
                        </label>
                        <label>Ký hiệu tiền tệ<input v-model="form.currency_symbol" placeholder="ví dụ: ₫" maxlength="12"><small v-if="form.errors.currency_symbol">{{ form.errors.currency_symbol }}</small></label>
                    </div>
                    <footer class="actions"><button class="btn outline" type="button" @click="back">Quay lại</button><button class="btn primary" type="button" @click="next">Tiếp tục</button></footer>
                </section>

                <section v-else>
                    <h1>Xác nhận</h1>
                    <dl class="summary">
                        <div><dt>Database</dt><dd>{{ form.database_driver.toUpperCase() }} · {{ form.database_name }}</dd><button type="button" @click="step = 0">Sửa</button></div>
                        <div v-if="form.database_driver !== 'sqlite'"><dt>Máy chủ</dt><dd>{{ form.database_host }}:{{ form.database_port }} · {{ form.database_username }}</dd><button type="button" @click="step = 0">Sửa</button></div>
                        <div><dt>Quản trị</dt><dd>{{ form.admin_username }} · {{ form.admin_name }}</dd><button type="button" @click="step = 1">Sửa</button></div>
                        <div><dt>Cửa hàng</dt><dd>{{ form.shop_name }} · {{ localeLabels[form.app_locale] }} · {{ formatDate(form.date_format) }} · {{ form.currency_symbol }}</dd><button type="button" @click="step = 2">Sửa</button></div>
                        <div><dt>Liên hệ</dt><dd>{{ form.admin_phone }} · {{ form.admin_email }}</dd><button type="button" @click="step = 1">Sửa</button></div>
                        <div v-if="isLinux"><dt>Lịch chạy nền</dt><dd :class="cronInstalled || form.install_cron ? 'good' : 'warn'">{{ cronInstalled ? 'Đã bật sẵn' : form.install_cron ? 'BẬT' : 'TẮT (không tự sao lưu)' }}</dd><button v-if="!cronInstalled" type="button" @click="form.install_cron = !form.install_cron">Đổi</button></div>
                    </dl>
                    <div v-if="isLinux" class="cron" :class="{ on: cronInstalled || form.install_cron }">
                        <div v-if="cronInstalled" class="cron-head"><span class="state on">✓ ĐÃ BẬT SẴN</span><b>Lịch chạy nền (cron)</b></div>
                        <label v-else class="cron-head switch">
                            <input v-model="form.install_cron" type="checkbox">
                            <span class="track" aria-hidden="true"><span class="knob"></span></span>
                            <span class="state" :class="{ on: form.install_cron }">{{ form.install_cron ? '✓ BẬT' : '✗ TẮT' }}</span>
                            <b>Lịch chạy nền (cron)</b>
                        </label>
                        <p class="cron-desc">Máy chủ cần một "đồng hồ" chạy mỗi phút để website tự làm các việc định kỳ mà không cần ai mở trang:</p>
                        <ul class="cron-list">
                            <li><b>Sao lưu tự động</b> theo giờ bạn hẹn trong Cài đặt</li>
                            <li><b>Nhắc việc</b> mỗi ngày lúc 08:00 và <b>nhắc công nợ</b> thứ Hai hằng tuần</li>
                            <li><b>Dọn dữ liệu cũ</b> hằng đêm (lịch sử ca làm việc, file xuất, lượt khách ra vào)</li>
                        </ul>
                        <p class="cron-desc"><b>Nên để BẬT.</b> Nếu TẮT, các việc trên sẽ không tự chạy (đặc biệt là sao lưu tự động). Bật lại sau bằng lệnh <code>sudo bash scripts/install-ubuntu.sh</code>.</p>
                    </div>
                    <p v-if="errors.database" class="banner error">{{ errors.database }}</p>
                    <footer class="actions"><button class="btn outline" type="button" :disabled="form.processing" @click="back">Quay lại</button><button class="btn primary big" type="button" :disabled="form.processing" @click="install">{{ form.processing ? 'Đang khởi tạo…' : 'Khởi tạo' }}</button></footer>
                </section>
            </template>
        </main>

        <details v-if="certificateUrl" class="cert">
            <summary>🔒 Cài chứng chỉ tin cậy để hết cảnh báo "Không bảo mật" và dùng được camera</summary>
            <p>Làm một lần cho mỗi thiết bị (điện thoại, máy tính). Bấm tải chứng chỉ, rồi cài theo hướng dẫn:</p>
            <a :href="certificateUrl" class="btn sm" download="rootCA.crt">Tải chứng chỉ (rootCA.crt)</a>
            <ul>
                <li><b>Android:</b> Cài đặt → Bảo mật → Cài đặt chứng chỉ → <b>Chứng chỉ CA</b> → chọn file vừa tải.</li>
                <li><b>iPhone (dùng Safari):</b> mở link tải → Cho phép → Cài đặt → <i>Đã tải hồ sơ</i> → Cài đặt. Sau đó Cài đặt → Cài đặt chung → Giới thiệu → <b>Cài đặt tin cậy chứng nhận</b> → bật <i>QLBH Local CA</i>.</li>
                <li><b>Windows (Chrome):</b> mở file → Install Certificate → Local Machine → <i>Trusted Root Certification Authorities</i>.</li>
            </ul>
            <p class="cert-note">Cài xong, mở lại trang bằng <b>https://</b>: không còn cảnh báo và trình duyệt sẽ xin quyền dùng camera.</p>
        </details>
    </div>
</template>

<style scoped>
:global(body){margin:0;background:#f4f6f4;color:#17231d;font-family:system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;font-size:15px}
.shell{max-width:720px;margin:0 auto;padding:24px 16px 48px}
.brand{display:flex;align-items:center;gap:12px;font-weight:700;font-size:19px;margin-bottom:18px}
.mark{width:40px;height:40px;border-radius:12px;background:#157b59;color:#fff;display:grid;place-items:center;font-size:20px}
.card{background:#fff;border:1px solid #e3e8e4;border-radius:20px;padding:32px;box-shadow:0 12px 40px #1c33231a}
h1{font-size:26px;line-height:1.2;margin:0 0 20px;letter-spacing:-.4px}
.steps{list-style:none;margin:0 0 26px;padding:0;display:flex;gap:10px}
.steps li{flex:1;display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:12px;background:#f4f6f4;color:#7d8a83;font-weight:600;font-size:15px}
.steps li.active{background:#e6f4ee;color:#0f6045}
.steps li.done{color:#157b59}
.num{flex:none;width:26px;height:26px;border-radius:50%;background:#fff;border:1px solid #d5ddd7;display:grid;place-items:center;font-size:13px}
.steps li.active .num{background:#157b59;border-color:#157b59;color:#fff}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
label{display:block;font-size:14px;font-weight:600;color:#3d4d44}
input,select{display:block;width:100%;box-sizing:border-box;height:46px;margin-top:7px;padding:0 14px;border:1px solid #cfd8d2;border-radius:12px;background:#fff;color:#17231d;font:inherit;font-size:16px;outline:0}
input:focus,select:focus{border-color:#157b59;box-shadow:0 0 0 4px #e6f4ee}
small.hint{color:#69786f;font-weight:400}
small{display:block;color:#b53f35;font-size:13px;margin-top:6px;font-weight:500}
.password{position:relative;display:block}.password input{padding-right:52px}
.password button.eye{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:36px;height:36px;padding:0;display:grid;place-items:center;background:none;border:0;border-radius:8px;color:#5f6f66;cursor:pointer}.password button.eye:hover{background:#eef3f0;color:#157b59}
.env{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:14px;background:#fff8e8;margin-bottom:12px;flex-wrap:wrap}
.env.bad{background:#fff3f0}
.env-icon{flex:none;width:28px;height:28px;border-radius:50%;background:#b0832f;color:#fff;display:grid;place-items:center;font-weight:700}
.env.bad .env-icon{background:#c9493c}
.env-text{flex:1;min-width:140px}.env-text b{font-size:15px}
.checks{list-style:none;margin:0 0 16px;padding:12px 14px;border:1px solid #e3e8e4;border-radius:14px;display:grid;gap:10px;font-size:14px}
.checks li{display:flex;gap:10px}.checks li>span{flex:none;width:16px;font-weight:700}
.checks li.ok>span{color:#157b59}.checks li.error>span{color:#c9493c}.checks li.warning>span{color:#b0832f}
.checks small{display:inline;color:#69786f;font-size:14px;font-weight:400}
.checks code{display:block;margin-top:6px;padding:9px 11px;border-radius:8px;background:#17231d;color:#e8f5ee;font-size:13px;word-break:break-all;user-select:all}
.actions{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:26px;padding-top:20px;border-top:1px solid #e9eee9}
.btn{height:46px;padding:0 22px;border-radius:12px;font:inherit;font-size:16px;font-weight:700;cursor:pointer;border:1px solid transparent}
.btn.primary{background:#157b59;color:#fff}.btn.primary:hover{background:#0f6045}.btn:disabled{opacity:.6;cursor:wait}
.btn.outline{background:#fff;border-color:#cfd8d2;color:#2c4137}.btn.ghost{background:transparent;color:#2c4137;height:38px;padding:0 12px;font-size:14px}
.btn.sm{height:38px;padding:0 14px;font-size:14px}
.btn.big{height:50px;font-size:17px;padding:0 30px}
.banner{margin:16px 0 0;padding:12px 14px;border-radius:12px;font-size:14px;line-height:1.5}
.banner.error{background:#fff2ef;color:#9c3b30}.banner.success{background:#edf7f0;color:#1f6b42}
.summary{margin:0;border:1px solid #e3e8e4;border-radius:14px;overflow:hidden}
.summary>div{display:flex;align-items:center;gap:14px;padding:13px 16px;border-bottom:1px solid #e9eee9}.summary>div:last-child{border:0}
.summary dt{width:100px;flex:none;color:#69786f;font-size:14px}.summary dd{margin:0;flex:1;font-weight:600;word-break:break-word}
.summary button{background:none;border:0;color:#157b59;font:inherit;font-size:14px;font-weight:600;cursor:pointer}
.check{display:flex;align-items:center;gap:10px;margin-top:16px;font-size:15px;font-weight:500;cursor:pointer}
.check input{width:18px;height:18px;margin:0;accent-color:#157b59}
.done{text-align:center;padding:22px 0 8px}
.done-icon{width:80px;height:80px;margin:0 auto 20px;border-radius:50%;background:#157b59;color:#fff;display:grid;place-items:center;font-size:42px;box-shadow:0 0 0 10px #e6f4ee}
.done h1{margin-bottom:12px}.done-sub{font-size:17px;color:#3d4d44;margin:0 0 6px}.done .note{color:#69786f;font-size:14px;margin:10px 0 0}
.done .btn{margin-top:0}
.save-warn{margin:0 0 14px;padding:10px 14px;border-radius:12px;background:#fff8e8;color:#7a5a12;font-size:14px}
.info{text-align:left;border:1px solid #e3e8e4;border-radius:14px;padding:6px 16px 10px;margin:0 0 14px}
.info h2{font-size:14px;margin:12px 0 4px;color:#157b59;text-transform:uppercase;letter-spacing:.4px}
.info dl{margin:0}
.info dl>div{display:flex;gap:12px;padding:7px 0;border-bottom:1px solid #eef2ef}
.info dl>div:last-child{border-bottom:0}
.info dt{width:120px;flex:none;color:#69786f;font-size:14px}
.info dd{margin:0;font-weight:600;word-break:break-all;user-select:all}
.cred{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;padding:16px;border:2px solid #157b59;border-radius:16px;background:#e6f4ee;text-align:left}.cred span{display:block;font-size:13px;font-weight:600;color:#3d4d44}.cred strong{display:block;margin-top:4px;font-size:20px;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#0f5a43;word-break:break-all;user-select:all}@media(max-width:560px){.cred{grid-template-columns:1fr}}
.done-actions{display:flex;flex-wrap:wrap;gap:10px;justify-content:center;align-items:center;margin-top:18px}
@media(max-width:700px){.card{padding:20px 16px;border-radius:16px}.grid{grid-template-columns:1fr;gap:18px}.steps .label{display:none}.steps li{justify-content:center}.actions{flex-direction:column-reverse;align-items:stretch}.actions .btn{width:100%}.summary>div{flex-wrap:wrap}.summary dt{width:100%}h1{font-size:22px}}
.cert{margin-top:16px;background:#fff;border:1px solid #e3e8e4;border-radius:14px;padding:12px 16px;font-size:14px;color:#41524a}
.cert summary{cursor:pointer;font-weight:600;color:#17231d}
.cert p{margin:10px 0}
.cert ul{margin:12px 0 0;padding-left:18px;display:grid;gap:8px}
.cert .btn{display:inline-flex;align-items:center;text-decoration:none}
.cert-note{color:#69786f}
.cron{margin-top:16px;padding:14px 16px;border:1px solid #e3e8e4;border-left:4px solid #c9493c;border-radius:14px;background:#fff;font-size:14px;color:#41524a}
.cron.on{border-left-color:#157b59;background:#f4fbf7}
.cron-head{display:flex;align-items:center;gap:10px;font-size:15px;cursor:default;flex-wrap:wrap}
.cron-head.switch{cursor:pointer}
.cron-head.switch input{position:absolute;opacity:0;width:0;height:0}
.track{flex:none;width:46px;height:26px;border-radius:13px;background:#c4cdc7;position:relative;transition:background .15s}
.knob{position:absolute;top:3px;left:3px;width:20px;height:20px;border-radius:50%;background:#fff;box-shadow:0 1px 3px #0003;transition:transform .15s}
.switch input:checked + .track{background:#157b59}
.switch input:checked + .track .knob{transform:translateX(20px)}
.switch input:focus-visible + .track{outline:3px solid #157b5955}
.state{padding:3px 10px;border-radius:999px;background:#fbe4e1;color:#b53f35;font-weight:800;font-size:12px;letter-spacing:.3px}
.state.on{background:#d7f0e4;color:#0f6a49}
.cron-desc{margin:10px 0 0}
.cron-list{margin:8px 0 0;padding-left:20px;display:grid;gap:4px}
.cron code{padding:2px 6px;border-radius:6px;background:#eef2ef;font-size:13px}
.good{color:#0f6a49}.warn{color:#b53f35}
.hint-line{margin:6px 0 0;color:#69786f;font-size:13px}
.lead{margin:-8px 0 18px;color:#5a6b62;font-size:14px}
</style>

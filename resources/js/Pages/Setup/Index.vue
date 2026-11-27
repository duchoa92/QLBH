<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    environment: { type: Object, default: () => ({ checks: [], drivers: {}, cron: {}, ready: true, os: '' }) },
    prepareResults: { type: Array, default: null },
    savedDatabase: { type: Object, default: null },
    done: { type: Object, default: null },
});

const step = ref(0);
const status = ref('');
const showPassword = ref(false);
const showConfirmation = ref(false);
const showDatabasePassword = ref(false);
const showEnvironment = ref(false);
const preparing = ref(false);
const page = usePage();
const form = useForm({
    database_driver: 'mysql', database_host: '127.0.0.1', database_port: '3306',
    database_name: props.savedDatabase ? 'qlbh' : '', database_username: props.savedDatabase?.username ?? 'root', database_password: '',
    admin_username: 'admin', admin_name: '', admin_phone: '', admin_email: '',
    admin_password: '', admin_password_confirmation: '',
    install_cron: props.environment.os === 'Linux',
});
const steps = ['Database', 'Quản trị', 'Xác nhận'];
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
    form.clearErrors();
    step.value = Math.min(step.value + 1, 2);
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
                <p class="done-sub">Tài khoản quản trị: <b>{{ done.username }}</b></p>
                <p v-if="done.notes" class="note">{{ done.notes }}</p>
                <button class="btn primary big" type="button" @click="goLogin">Đăng nhập</button>
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
                            <input v-model="form.database_name" placeholder="qlbh"><small v-if="form.errors.database_name">{{ form.errors.database_name }}</small>
                        </label>
                        <template v-if="form.database_driver !== 'sqlite'">
                            <label>Máy chủ<input v-model="form.database_host" placeholder="127.0.0.1"><small v-if="form.errors.database_host">{{ form.errors.database_host }}</small></label>
                            <label>Cổng<input v-model="form.database_port" inputmode="numeric" placeholder="3306"></label>
                            <label>Tài khoản<input v-model="form.database_username" placeholder="root"><small v-if="form.errors.database_username">{{ form.errors.database_username }}</small></label>
                            <label>Mật khẩu
                                <span class="password"><input v-model="form.database_password" :type="showDatabasePassword ? 'text' : 'password'" :placeholder="savedHint ? '••••••••  (đã đặt sẵn)' : ''" autocomplete="off"><button type="button" @click="showDatabasePassword = !showDatabasePassword">{{ showDatabasePassword ? 'Ẩn' : 'Hiện' }}</button></span>
                                <small v-if="savedHint" class="hint">Mật khẩu đã được đặt sẵn khi chạy script cài đặt. Để trống ô này để dùng mật khẩu đó.</small>
                            </label>
                        </template>
                    </div>
                    <p v-if="errors.database" class="banner error">{{ errors.database }}</p>
                    <p v-if="status" class="banner success">{{ status }}</p>
                    <footer class="actions"><button class="btn outline" type="button" :disabled="form.processing" @click="testConnection">Kiểm tra kết nối</button><button class="btn primary" type="button" @click="next">Tiếp tục</button></footer>
                </section>

                <!-- Bước 2: quản trị viên -->
                <section v-else-if="step === 1">
                    <h1>Quản trị viên</h1>
                    <div class="grid">
                        <label>Tên đăng nhập<input v-model="form.admin_username" autocomplete="username"><small v-if="form.errors.admin_username">{{ form.errors.admin_username }}</small></label>
                        <label>Họ và tên<input v-model="form.admin_name" placeholder="Đức Hòa" autocomplete="name"><small v-if="form.errors.admin_name">{{ form.errors.admin_name }}</small></label>
                        <label>Số điện thoại<input v-model="form.admin_phone" type="tel" placeholder="0906064789" autocomplete="tel"><small v-if="form.errors.admin_phone">{{ form.errors.admin_phone }}</small></label>
                        <label>Email<input v-model="form.admin_email" type="email" placeholder="duchoa.92@gmail.com" autocomplete="email"><small v-if="form.errors.admin_email">{{ form.errors.admin_email }}</small></label>
                        <label>Mật khẩu
                            <span class="password"><input v-model="form.admin_password" :type="showPassword ? 'text' : 'password'" placeholder="Từ 8 ký tự" autocomplete="new-password"><button type="button" @click="showPassword = !showPassword">{{ showPassword ? 'Ẩn' : 'Hiện' }}</button></span>
                            <small v-if="form.errors.admin_password">{{ form.errors.admin_password }}</small>
                        </label>
                        <label>Nhập lại mật khẩu
                            <span class="password"><input v-model="form.admin_password_confirmation" :type="showConfirmation ? 'text' : 'password'" autocomplete="new-password"><button type="button" @click="showConfirmation = !showConfirmation">{{ showConfirmation ? 'Ẩn' : 'Hiện' }}</button></span>
                            <small v-if="form.errors.admin_password_confirmation">{{ form.errors.admin_password_confirmation }}</small>
                        </label>
                    </div>
                    <footer class="actions"><button class="btn outline" type="button" @click="back">Quay lại</button><button class="btn primary" type="button" @click="next">Tiếp tục</button></footer>
                </section>

                <!-- Bước 3: xác nhận -->
                <section v-else>
                    <h1>Xác nhận</h1>
                    <dl class="summary">
                        <div><dt>Database</dt><dd>{{ form.database_driver.toUpperCase() }} · {{ form.database_name }}</dd><button type="button" @click="step = 0">Sửa</button></div>
                        <div v-if="form.database_driver !== 'sqlite'"><dt>Máy chủ</dt><dd>{{ form.database_host }}:{{ form.database_port }} · {{ form.database_username }}</dd><button type="button" @click="step = 0">Sửa</button></div>
                        <div><dt>Quản trị</dt><dd>{{ form.admin_username }} · {{ form.admin_name }}</dd><button type="button" @click="step = 1">Sửa</button></div>
                        <div><dt>Liên hệ</dt><dd>{{ form.admin_phone }} · {{ form.admin_email }}</dd><button type="button" @click="step = 1">Sửa</button></div>
                    </dl>
                    <label v-if="isLinux" class="check"><input v-model="form.install_cron" type="checkbox"><span>Tự cài lịch chạy nền (cron)</span></label>
                    <p v-if="errors.database" class="banner error">{{ errors.database }}</p>
                    <footer class="actions"><button class="btn outline" type="button" :disabled="form.processing" @click="back">Quay lại</button><button class="btn primary big" type="button" :disabled="form.processing" @click="install">{{ form.processing ? 'Đang khởi tạo…' : 'Khởi tạo' }}</button></footer>
                </section>
            </template>
        </main>
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
.password{position:relative;display:block}.password input{padding-right:68px}
.password button{position:absolute;right:6px;top:11px;height:36px;padding:0 10px;background:none;border:0;color:#5f6f66;font:inherit;font-size:14px;cursor:pointer}
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
.done .btn{margin-top:24px}
@media(max-width:700px){.card{padding:20px 16px;border-radius:16px}.grid{grid-template-columns:1fr;gap:18px}.steps .label{display:none}.steps li{justify-content:center}.actions{flex-direction:column-reverse;align-items:stretch}.actions .btn{width:100%}.summary>div{flex-wrap:wrap}.summary dt{width:100%}h1{font-size:22px}}
</style>

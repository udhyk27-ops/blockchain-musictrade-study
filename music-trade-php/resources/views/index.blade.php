<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Music Royalty</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 18px; margin-bottom: 10px; }
        .section { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; }
        h2 { font-size: 14px; margin-bottom: 10px; }
        input, select { padding: 6px 8px; margin: 3px; border: 1px solid #ccc; font-size: 13px; }
        button { padding: 6px 14px; margin: 3px; background: #fff; border: 1px solid #999; cursor: pointer; font-size: 13px; }
        button:hover { background: #eee; }
        button:disabled { background: #eee; color: #aaa; cursor: not-allowed; }
        .result { margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; display: none; }
        .result.error { border-color: #f99; background: #fff0f0; }
        pre { font-size: 12px; white-space: pre-wrap; word-break: break-all; }
        .holder-row { margin: 4px 0; }
        .section-label { font-size: 11px; color: #888; margin-bottom: 6px; }
        .user-bar { display: flex; justify-content: flex-end; gap: 2rem; align-items: center; margin-bottom: 2rem; font-size: 13px; }
    </style>
</head>
<body>

<h1 style="text-align: center;">블록체인 음악 저작권 정산</h1>

<div class="user-bar">
    <div>
        <b>아이디:</b> {{ Auth::user()->f_id }}<br>
        <b>지갑주소:</b> <span style="font-size:11px;">{{ Auth::user()->f_wallet_address ?? '없음' }}</span>
    </div>
    <form method="post" action="{{ route('logout') }}">
        @csrf
        <button type="submit">로그아웃</button>
    </form>
</div>

<div style="display: flex; gap: 20px; align-items: flex-start;">

    {{-- 왼쪽: 조회 기능 --}}
    <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
        <h2 style="font-size:16px; color:#888;">조회</h2>

        {{-- 곡 정보 조회 --}}
        <div class="section">
            <h2>곡 정보 조회</h2>
            <p class="section-label">DB 조회 (블록체인 Song ID 기준)</p>
            Song ID: <input type="number" id="info-songId" placeholder="Song ID" style="width:80px">
            <button onclick="getSongInfo()">조회</button>
            <div id="info-result" class="result"></div>
        </div>

        {{-- 지분율 조회 --}}
        <div class="section">
            <h2>지분율 조회</h2>
            <p class="section-label">DB 조회</p>
            Song ID: <input type="number" id="holders-songId" placeholder="Song ID" style="width:80px">
            <button onclick="getHolders()">조회</button>
            <div id="holders-result" class="result"></div>
        </div>

        {{-- 라이선스 구매 이력 --}}
        <div class="section">
            <h2>라이선스 구매 이력</h2>
            <p class="section-label">Song ID 비우면 전체 조회</p>
            Song ID: <input type="number" id="license-songId" placeholder="전체 조회시 입력X" style="width:150px">
            <button onclick="getLicenseHistory()">조회</button>
            <div id="license-result" class="result"></div>
        </div>

        {{-- 내 정산 이력 --}}
        <div class="section">
            <h2>내 정산 이력</h2>
            <p class="section-label">내 지갑 기준 전체 조회</p>
            <button onclick="getRoyaltyHistory()">새로고침</button>
            <div id="royalty-result" class="result"></div>
        </div>

        {{-- 전체 곡 수 --}}
        <div class="section">
            <h2>전체 등록 곡 수</h2>
            <button onclick="getSongCount()">새로고침</button>
            <div id="count-result" class="result"></div>
        </div>
    </div>

    {{-- 구분선 --}}
    <div style="width: 1px; background: #ddd; align-self: stretch;"></div>

    {{-- 오른쪽: 트랜잭션 --}}
    <div style="flex: 1; display: flex; flex-direction: column; gap: 15px;">
        <h2 style="font-size:16px; color:#888;">트랜잭션 (서버 서명)</h2>

        {{-- 곡 등록 --}}
        <div class="section">
            <h2>곡 등록 (registerSong)</h2>
            <p class="section-label">서버에서 서명 후 Besu에 전송</p>
            <input type="text" id="reg-title" placeholder="곡 제목">
            <button onclick="registerSong(event)">등록</button>
            <div id="reg-result" class="result"></div>
        </div>

        {{-- 지분율 설정 --}}
        <div class="section">
            <h2>지분율 설정 (setShares)</h2>
            <p class="section-label">곡 등록자만 가능 | 합계 100% (10000)</p>
            Song ID: <input type="number" id="shares-songId" placeholder="Song ID" style="width:80px">
            <div id="holders-wrap">
                <div class="holder-row">
                    <select class="h-user">
                        @foreach($users as $user)
                            <option value="{{ $user->f_no }}">{{ $user->f_id }}</option>
                        @endforeach
                    </select>
                    <select class="h-role">
                        <option value="1">음반 제작사</option>
                        <option value="2">작곡가</option>
                        <option value="3">작사가</option>
                        <option value="4">가수</option>
                        <option value="5">편곡자</option>
                    </select>
                    <input type="number" class="h-share" placeholder="지분(100%=10000)" style="width:120px">
                </div>
            </div>
            <button onclick="addHolder()">+ 홀더 추가</button>
            <button onclick="setShares(event)">지분율 설정</button>
            <div id="shares-result" class="result"></div>
        </div>

        {{-- 라이선스 구매 --}}
        <div class="section">
            <h2>라이선스 구매 (purchaseLicense)</h2>
            <p class="section-label">구매 즉시 권리자에게 자동 정산</p>
            Song ID: <input type="number" id="buy-songId" placeholder="Song ID" style="width:80px">
            금액(AID): <input type="number" id="buy-amount" value="0.01" step="0.001" style="width:80px">
            <button onclick="purchaseLicense(event)">구매</button>
            <div id="buy-result" class="result"></div>
        </div>
    </div>

</div>

<script>
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;

    const ROLE_NAMES = {
        1: '음반 제작사', 2: '작곡가', 3: '작사가', 4: '가수', 5: '편곡자'
    };

    // ── 공통 유틸 ────────────────────────────────────
    function show(id, data, isError = false) {
        const el = document.getElementById(id);
        el.style.display = 'block';
        el.className = 'result' + (isError ? ' error' : '');
        el.innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
    }

    function setLoading(btn, loading) {
        btn.disabled = loading;
        btn.dataset.orig = btn.dataset.orig ?? btn.textContent;
        btn.textContent = loading ? '처리중...' : btn.dataset.orig;
    }

    async function apiFetch(url, method = 'GET', body = null) {
        const opts = {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'Accept': 'application/json',
            },
        };
        if (body) opts.body = JSON.stringify(body);
        const res = await fetch(url, opts);
        return res.json();
    }

    // ── 페이지 로드 ──────────────────────────────────
    window.addEventListener('load', () => {
        getSongCount();
        getRoyaltyHistory();
    });

    // ── 1. 곡 등록 ──────────────────────────────────
    async function registerSong(e) {
        const title = document.getElementById('reg-title').value.trim();
        if (!title) { alert('제목을 입력하세요.'); return; }

        const btn = e.currentTarget;
        setLoading(btn, true);
        try {
            const data = await apiFetch('{{ route("song.register") }}', 'POST', { title });
            show('reg-result', data, !data.success);
        } catch (err) {
            show('reg-result', { error: err.message }, true);
        } finally {
            setLoading(btn, false);
        }
    }

    // ── 2. 홀더 추가 ─────────────────────────────────
    function addHolder() {
        const userOptions = `
        @foreach($users as $user)
        <option value="{{ $user->f_no }}">{{ $user->f_id }}</option>
        @endforeach
        `;
        const div = document.createElement('div');
        div.className = 'holder-row';
        div.innerHTML = `
            <select class="h-user">${userOptions}</select>
            <select class="h-role">
                <option value="1">음반 제작사</option>
                <option value="2">작곡가</option>
                <option value="3">작사가</option>
                <option value="4">가수</option>
                <option value="5">편곡자</option>
            </select>
            <input type="number" class="h-share" placeholder="지분(100%=10000)" style="width:120px">
            <button onclick="this.parentElement.remove()">삭제</button>
        `;
        document.getElementById('holders-wrap').appendChild(div);
    }

    // ── 2. 지분율 설정 ───────────────────────────────
    async function setShares(e) {
        const songId  = parseInt(document.getElementById('shares-songId').value);
        const userNos = [...document.querySelectorAll('.h-user')].map(el => parseInt(el.value));
        const roles   = [...document.querySelectorAll('.h-role')].map(el => parseInt(el.value));
        const shares  = [...document.querySelectorAll('.h-share')].map(el => parseInt(el.value));

        if (!songId) { alert('Song ID를 입력하세요.'); return; }
        if (shares.some(s => isNaN(s) || s <= 0)) { alert('지분율을 모두 입력하세요.'); return; }
        if (shares.reduce((a, b) => a + b, 0) !== 10000) {
            alert('지분율 합계가 10000(100%)이어야 합니다.');
            return;
        }

        const holders = userNos.map((no, i) => ({
            user_no: no,
            role:    roles[i],
            share:   shares[i],
        }));

        const btn = e.currentTarget;
        setLoading(btn, true);
        try {
            const data = await apiFetch('{{ route("song.setShares") }}', 'POST', {
                song_id: songId,
                holders,
            });
            show('shares-result', data, !data.success);
        } catch (err) {
            show('shares-result', { error: err.message }, true);
        } finally {
            setLoading(btn, false);
        }
    }

    // ── 3. 라이선스 구매 ─────────────────────────────
    async function purchaseLicense(e) {
        const songId = parseInt(document.getElementById('buy-songId').value);
        const amount = document.getElementById('buy-amount').value;
        if (!songId) { alert('Song ID를 입력하세요.'); return; }
        if (!amount || parseFloat(amount) <= 0) { alert('금액을 입력하세요.'); return; }

        const btn = e.currentTarget;
        setLoading(btn, true);
        try {
            const data = await apiFetch('{{ route("song.purchase") }}', 'POST', {
                song_id: songId,
                amount,
            });
            show('buy-result', data, !data.success);
        } catch (err) {
            show('buy-result', { error: err.message }, true);
        } finally {
            setLoading(btn, false);
        }
    }

    // ── 4. 곡 정보 조회 ─────────────────────────────
    async function getSongInfo() {
        const songId = document.getElementById('info-songId').value;
        if (!songId) { alert('Song ID를 입력하세요.'); return; }
        try {
            const data = await apiFetch(`{{ route("song.info") }}?song_id=${songId}`);
            show('info-result', data.success ? data.info : data, !data.success);
        } catch (err) {
            show('info-result', { error: err.message }, true);
        }
    }

    // ── 5. 지분율 조회 ───────────────────────────────
    async function getHolders() {
        const songId = document.getElementById('holders-songId').value;
        if (!songId) { alert('Song ID를 입력하세요.'); return; }
        try {
            const data = await apiFetch(`{{ route("song.holders") }}?song_id=${songId}`);
            if (data.success) {
                const mapped = data.holders.map(h => ({
                    ...h,
                    role: ROLE_NAMES[h.role] ?? `역할(${h.role})`,
                }));
                show('holders-result', { holders: mapped });
            } else {
                show('holders-result', data, true);
            }
        } catch (err) {
            show('holders-result', { error: err.message }, true);
        }
    }

    // ── 6. 라이선스 구매 이력 ────────────────────────
    async function getLicenseHistory() {
        const songId = document.getElementById('license-songId').value;
        const url    = '{{ route("license.history") }}' + (songId ? `?song_id=${songId}` : '');
        try {
            const data = await apiFetch(url);
            show('license-result', data.success ? data : data, !data.success);
        } catch (err) {
            show('license-result', { error: err.message }, true);
        }
    }

    // ── 7. 정산 이력 ─────────────────────────────────
    async function getRoyaltyHistory() {
        try {
            const data = await apiFetch('{{ route("royalty.history") }}');
            if (data.success) {
                const mapped = data.history.map(h => ({
                    ...h,
                    role: ROLE_NAMES[h.role] ?? `역할(${h.role})`,
                }));
                show('royalty-result', { count: data.count, history: mapped });
            } else {
                show('royalty-result', data, true);
            }
        } catch (err) {
            show('royalty-result', { error: err.message }, true);
        }
    }

    // ── 8. 전체 곡 수 ────────────────────────────────
    async function getSongCount() {
        try {
            const data = await apiFetch('{{ route("song.count") }}');
            show('count-result', data.success ? { songCount: data.songCount } : data, !data.success);
        } catch (err) {
            show('count-result', { error: err.message }, true);
        }
    }
</script>
</body>
</html>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Music Royalty</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Arial, sans-serif; max-width: 750px; margin: 40px auto; padding: 0 20px; }
        h1 { font-size: 18px; margin-bottom: 10px; }
        .wallet-bar { display: flex; align-items: center; gap: 10px; margin-bottom: 20px; padding: 10px; border: 1px solid #ddd; background: #f9f9f9; }
        .wallet-address { font-size: 12px; color: #333; word-break: break-all; }
        .disconnected { color: #999; font-size: 13px; }
        .section { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; }
        h2 { font-size: 14px; margin-bottom: 10px; }
        input, select { padding: 6px 8px; margin: 3px; border: 1px solid #ccc; font-size: 13px; }
        button { padding: 6px 14px; margin: 3px; background: #fff; border: 1px solid #999; cursor: pointer; font-size: 13px; }
        button:hover { background: #eee; }
        .result { margin-top: 10px; padding: 10px; background: #f9f9f9; border: 1px solid #ddd; display: none; }
        .error { border-color: red; background: #fff5f5; }
        pre { font-size: 12px; white-space: pre-wrap; word-break: break-all; }
        .holder-row { margin: 4px 0; }
    </style>
</head>
<body>

<h1>Music Royalty 블록체인 테스트</h1>

{{-- 지갑 연결 --}}
<div class="wallet-bar">
    <button id="connectBtn" onclick="connectWallet()">지갑 연결</button>
    <button id="disconnectBtn" onclick="disconnectWallet()" style="display:none">로그아웃</button>
    <span id="walletStatus" class="disconnected">연결되지 않음</span>
</div>

{{-- 1. 곡 등록 --}}
<div class="section">
    <h2>1. 곡 등록 (registerSong)</h2>
    <input type="text" id="reg-title" placeholder="곡 제목">
    <button onclick="registerSong()">등록</button>
    <div id="reg-result" class="result"></div>
</div>

{{-- 2. 지분율 설정 --}}
<div class="section">
    <h2>2. 지분율 설정 (setShares)</h2>
    Song ID: <input type="number" id="shares-songId" placeholder="Song ID" style="width:80px">
    <div id="holders-wrap">
        <div class="holder-row">
            <input type="text" class="h-wallet" placeholder="지갑주소 0x..." size="42">
            <select class="h-role">
                <option value="0">작곡가</option>
                <option value="1">작사가</option>
                <option value="2">제작사</option>
            </select>
            <input type="number" class="h-share" placeholder="지분%" style="width:60px">
        </div>
    </div>
    <button onclick="addHolder()">+ 홀더 추가</button>
    <button onclick="setShares()">지분율 설정</button>
    <div id="shares-result" class="result"></div>
</div>

{{-- 3. 라이선스 구매 --}}
<div class="section">
    <h2>3. 라이선스 구매 (purchaseLicense)</h2>
    Song ID: <input type="number" id="buy-songId" placeholder="Song ID" style="width:80px">
    금액(ETH): <input type="number" id="buy-amount" value="0.01" step="0.001" style="width:80px">
    <button onclick="purchaseLicense()">구매</button>
    <div id="buy-result" class="result"></div>
</div>

{{-- 4. 곡 정보 조회 --}}
<div class="section">
    <h2>4. 곡 정보 조회 (getSongInfo)</h2>
    Song ID: <input type="number" id="info-songId" placeholder="Song ID" style="width:80px">
    <button onclick="getSongInfo()">조회</button>
    <div id="info-result" class="result"></div>
</div>

{{-- 5. 홀더 조회 --}}
<div class="section">
    <h2>5. 홀더 조회 (getHolders)</h2>
    Song ID: <input type="number" id="holders-songId" placeholder="Song ID" style="width:80px">
    <button onclick="getHolders()">조회</button>
    <div id="holders-result" class="result"></div>
</div>

{{-- 6. 전체 곡 수 --}}
<div class="section">
    <h2>6. 전체 등록 곡 수 (getSongCount)</h2>
    <button onclick="getSongCount()">조회</button>
    <div id="count-result" class="result"></div>
</div>

{{-- ethers.js CDN --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/ethers/6.7.0/ethers.umd.min.js"></script>
<script>
    const CONTRACT_ADDR = '{{ config('besu.contract_address') }}';
    const CHAIN_ID      = {{ config('besu.chain_id') }};

    const ABI = [
        { name: 'getSongCount',     type: 'function', stateMutability: 'view',
            inputs: [], outputs: [{ type: 'uint256' }] },
        { name: 'getSongInfo',      type: 'function', stateMutability: 'view',
            inputs: [{ name: 'songId', type: 'uint256' }],
            outputs: [{ name: 'title', type: 'string' }, { name: 'producer', type: 'address' },
                { name: 'active', type: 'bool' }, { name: 'totalRevenue', type: 'uint256' },
                { name: 'holderCount', type: 'uint256' }] },
        { name: 'getHolders',       type: 'function', stateMutability: 'view',
            inputs: [{ name: 'songId', type: 'uint256' }],
            outputs: [{ name: 'wallets', type: 'address[]' }, { name: 'roles', type: 'uint8[]' },
                { name: 'shares', type: 'uint256[]' }] },
        { name: 'registerSong',     type: 'function', stateMutability: 'nonpayable',
            inputs: [{ name: 'title', type: 'string' }],
            outputs: [{ type: 'uint256' }] },
        { name: 'setShares',        type: 'function', stateMutability: 'nonpayable',
            inputs: [{ name: 'songId', type: 'uint256' }, { name: 'wallets', type: 'address[]' },
                { name: 'roles', type: 'uint8[]' }, { name: 'shares', type: 'uint256[]' }],
            outputs: [] },
        { name: 'purchaseLicense',  type: 'function', stateMutability: 'payable',
            inputs: [{ name: 'songId', type: 'uint256' }], outputs: [] },
    ];

    let provider = null;
    let signer   = null;
    let contract = null;
    let walletAddress = null;
    let isConnecting  = false;

    // ── 지갑 연결 ──────────────────────────────────────
    async function connectWallet() {
        if (typeof window.ethereum === 'undefined') { alert('MetaMask를 설치해주세요!'); return; }
        if (isConnecting) { alert('MetaMask 팝업이 이미 열려있습니다.'); return; }
        isConnecting = true;
        try {
            provider = new ethers.BrowserProvider(window.ethereum);

            // Besu 네트워크로 전환
            try {
                await provider.send('wallet_switchEthereumChain',
                    [{ chainId: '0x' + CHAIN_ID.toString(16) }]);
            } catch (e) {
                alert('MetaMask에서 Besu 네트워크로 전환해주세요! (Chain ID: ' + CHAIN_ID + ')');
                return;
            }

            // 계정 선택 팝업
            await provider.send('wallet_requestPermissions', [{ eth_accounts: {} }]);
            signer        = await provider.getSigner();
            walletAddress = await signer.getAddress();
            contract      = new ethers.Contract(CONTRACT_ADDR, ABI, signer);

            document.getElementById('walletStatus').textContent = walletAddress;
            document.getElementById('walletStatus').className   = 'wallet-address';
            document.getElementById('connectBtn').textContent   = '연결됨';
            document.getElementById('connectBtn').disabled      = true;
            document.getElementById('disconnectBtn').style.display = 'inline';

        } catch (e) {
            alert('지갑 연결 실패: ' + e.message);
        } finally {
            isConnecting = false;
        }
    }

    // ── 지갑 해제 ──────────────────────────────────────
    function disconnectWallet() {
        provider = signer = contract = walletAddress = null;
        document.getElementById('walletStatus').textContent     = '연결되지 않음';
        document.getElementById('walletStatus').className       = 'disconnected';
        document.getElementById('connectBtn').textContent       = '지갑 연결';
        document.getElementById('connectBtn').disabled          = false;
        document.getElementById('disconnectBtn').style.display  = 'none';
    }

    function checkWallet() {
        if (!contract) { alert('먼저 지갑을 연결해주세요!'); return false; }
        return true;
    }

    // ── 결과 표시 ──────────────────────────────────────
    function show(id, data, isError = false) {
        const el = document.getElementById(id);
        el.style.display = 'block';
        el.className = 'result' + (isError ? ' error' : '');
        el.innerHTML = '<pre>' + JSON.stringify(data, (k, v) =>
            typeof v === 'bigint' ? v.toString() : v, 2) + '</pre>';
    }

    // ── 1. 곡 등록 ──────────────────────────────────────
    async function registerSong() {
        if (!checkWallet()) return;
        const title = document.getElementById('reg-title').value.trim();
        if (!title) { alert('제목을 입력하세요.'); return; }
        try {
            const tx = await contract.registerSong(title);
            show('reg-result', { status: '트랜잭션 전송됨', txHash: tx.hash });
            const receipt = await tx.wait();
            // SongRegistered 이벤트에서 songId 파싱
            const songId = parseInt(receipt.logs[0].topics[1], 16);
            show('reg-result', { status: '완료', txHash: tx.hash, songId });
            // 다른 섹션 자동 입력
            document.getElementById('shares-songId').value  = songId;
            document.getElementById('buy-songId').value     = songId;
            document.getElementById('info-songId').value    = songId;
            document.getElementById('holders-songId').value = songId;
        } catch (e) {
            show('reg-result', { error: e.message }, true);
        }
    }

    // ── 2. 홀더 추가 ────────────────────────────────────
    function addHolder() {
        const div = document.createElement('div');
        div.className = 'holder-row';
        div.innerHTML = `
            <input type="text" class="h-wallet" placeholder="지갑주소 0x..." size="42">
            <select class="h-role">
                <option value="0">작곡가</option>
                <option value="1">작사가</option>
                <option value="2">제작사</option>
            </select>
            <input type="number" class="h-share" placeholder="지분%" style="width:60px">
            <button onclick="this.parentElement.remove()">삭제</button>`;
        document.getElementById('holders-wrap').appendChild(div);
    }

    // ── 2. 지분율 설정 ───────────────────────────────────
    async function setShares() {
        if (!checkWallet()) return;
        const songId  = parseInt(document.getElementById('shares-songId').value);
        const wallets = [...document.querySelectorAll('.h-wallet')].map(e => e.value.trim());
        const roles   = [...document.querySelectorAll('.h-role')].map(e => parseInt(e.value));
        const shares  = [...document.querySelectorAll('.h-share')].map(e => parseInt(e.value));
        if (!songId)                { alert('Song ID를 입력하세요.'); return; }
        if (wallets.some(w => !w))  { alert('지갑 주소를 모두 입력하세요.'); return; }
        if (shares.reduce((a,b) => a+b, 0) !== 100) { alert('지분율 합계가 100이어야 합니다.'); return; }
        try {
            const tx = await contract.setShares(songId, wallets, roles, shares);
            show('shares-result', { status: '트랜잭션 전송됨', txHash: tx.hash });
            await tx.wait();
            show('shares-result', { status: '완료', txHash: tx.hash });
        } catch (e) {
            show('shares-result', { error: e.message }, true);
        }
    }

    // ── 3. 라이선스 구매 ─────────────────────────────────
    async function purchaseLicense() {
        if (!checkWallet()) return;
        const songId = parseInt(document.getElementById('buy-songId').value);
        const eth    = document.getElementById('buy-amount').value;
        if (!songId) { alert('Song ID를 입력하세요.'); return; }
        try {
            const tx = await contract.purchaseLicense(songId,
                { value: ethers.parseEther(eth) });
            show('buy-result', { status: '트랜잭션 전송됨', txHash: tx.hash });
            await tx.wait();
            show('buy-result', { status: '완료', txHash: tx.hash, buyer: walletAddress, amount: eth + ' ETH' });
        } catch (e) {
            show('buy-result', { error: e.message }, true);
        }
    }

    // ── 4. 곡 정보 조회 ─────────────────────────────────
    async function getSongInfo() {
        if (!checkWallet()) return;
        const songId = parseInt(document.getElementById('info-songId').value);
        if (!songId) { alert('Song ID를 입력하세요.'); return; }
        try {
            const res = await contract.getSongInfo(songId);
            show('info-result', {
                title:        res.title,
                producer:     res.producer,
                active:       res.active,
                totalRevenue: res.totalRevenue.toString() + ' wei',
                holderCount:  res.holderCount.toString(),
            });
        } catch (e) {
            show('info-result', { error: e.message }, true);
        }
    }

    // ── 5. 홀더 조회 ────────────────────────────────────
    async function getHolders() {
        if (!checkWallet()) return;
        const songId = parseInt(document.getElementById('holders-songId').value);
        if (!songId) { alert('Song ID를 입력하세요.'); return; }
        try {
            const res = await contract.getHolders(songId);
            const roleNames = ['작곡가', '작사가', '제작사'];
            const holders = res.wallets.map((w, i) => ({
                wallet: w,
                role:   roleNames[Number(res.roles[i])] ?? res.roles[i].toString(),
                share:  res.shares[i].toString() + '%',
            }));
            show('holders-result', { holders });
        } catch (e) {
            show('holders-result', { error: e.message }, true);
        }
    }

    // ── 6. 전체 곡 수 ────────────────────────────────────
    async function getSongCount() {
        if (!checkWallet()) return;
        try {
            const count = await contract.getSongCount();
            show('count-result', { songCount: count.toString() });
        } catch (e) {
            show('count-result', { error: e.message }, true);
        }
    }
</script>
</body>
</html>

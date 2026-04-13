# 블록체인 음악 저작권 정산 시스템

## 블록체인 환경

### Hyperledger Besu
- VM IP: 192.168.56.101:8545
- chainId: 1337
- 합의 알고리즘: **QBFT**
- blockperiodseconds: 5 (5초마다 블록 생성)
- requesttimeoutseconds: 10
- 실행 명령어:
```bash

besu \
  --data-path=node1/data \
  --genesis-file=genesis.json \
  --rpc-http-enabled=true \
  --rpc-http-api=ETH,NET,QBFT \
  --host-allowlist="*" \
  --rpc-http-cors-origins="all" \
  --rpc-http-host=0.0.0.0 \
  --rpc-http-port=8545 \
  --min-gas-price=0 \
  --sync-mode=FULL \
  --logging=INFO

```

### genesis.json
```json
{
  "config": {
    "chainId": 1337,
    "homesteadBlock": 0,
    "eip150Block": 0,
    "eip155Block": 0,
    "eip158Block": 0,
    "byzantiumBlock": 0,
    "constantinopleBlock": 0,
    "petersburgBlock": 0,
    "istanbulBlock": 0,
    "berlinBlock": 0,
    "londonBlock": 0,
    "qbft": {
      "blockperiodseconds": 5,
      "epochlength": 30000,
      "requesttimeoutseconds": 10
    }
  },
  "nonce": "0x0",
  "timestamp": "0x58ee40ba",
  "gasLimit": "0x47b760",
  "difficulty": "0x1",
  "mixHash": "0x63746963616c2062797a616e74696e65206661756c7420746f6c6572616e6365",
  "coinbase": "0x0000000000000000000000000000000000000000",
  "extraData": "0xf83aa00000000000000000000000000000000000000000000000000000000000000000d5942a923ec5b350f7a99ff3c5868ae0bc5e07bd7097c080c0",
  "alloc": {
    "0x1f7a4A61dc265B1F073cd4cA9F6adD499035A689": { "balance": "0x56BC75E2D63100000" },
    "0xE7Ca71CacabBCc510ad1835df19F1D334B8Bb183": { "balance": "0x56BC75E2D63100000" },
    "0x0E217137e76482521f72E845Ce24e55B01ce7D8B": { "balance": "0x56BC75E2D63100000" }
  }
}
```

### 체인 리셋 시 주의사항
- `node1/data` 삭제 시 key 파일도 삭제됨 → 노드 주소 바뀜 → extraData 재생성 필요
- 안전한 리셋 방법:
```bash
pkill besu
cp node1/data/key ./key.backup        # 키 백업
rm -rf node1/data/database
rm -rf node1/data/caches
rm -f node1/data/DATABASE_METADATA.json
mkdir -p node1/data
cp ./key.backup node1/data/key        # 키 복원
# Besu 재시작
```

---

## 컨트랙트

### 배포 정보
- 파일: `music-trade-hardhat/contracts/MusicRoyalty.sol`
- 배포 주소: `0xBC69Cf59bbF7728d0C2984398f5A6C4E7D1DC437`
- 배포 계정: `0x1f7a4A61dc265B1F073cd4cA9F6adD499035A689`

### 역할 상수
```
ROLE_PRODUCER = 1  // 음반 제작사
ROLE_COMPOSER = 2  // 작곡가
ROLE_LYRICIST = 3  // 작사가
ROLE_VOCALIST = 4  // 가수
ROLE_ARRANGER = 5  // 편곡자
```

### 주요 함수
```
registerSong(title)                          → 곡 등록 (nonpayable)
setShares(songId, wallets[], roles[], shares[]) → 지분율 설정 (nonpayable, 등록자만)
purchaseLicense(songId)                      → 라이선스 구매 + 자동 정산 (payable)
getSongInfo(songId)                          → 곡 정보 조회 (view)
getHolders(songId)                           → 홀더 조회 (view)
getSongCount()                               → 전체 곡 수 (view)
```

### 지분율 규칙
- 컨트랙트 내부: 10000 basis point (10000 = 100%)
- 프론트 입력: 100 기준 → 컨트랙트 전송 시 × 100 자동 변환
- setShares는 active=false 상태에서만 1회 호출 가능

### 이벤트
```
SongRegistered(songId, title, producer)
SharesSet(songId)
SongActivated(songId, title)
LicensePurchased(songId, buyer, amount, timestamp)
RoyaltyPaid(songId, recipient, role, amount)
```

---

## MetaMask 계정
```
계정 1 (음반 제작사/배포자): 0x1f7a4A61dc265B1F073cd4cA9F6adD499035A689  (100 ETH)
계정 2 (작곡가/권리자):      0xE7Ca71CacabBCc510ad1835df19F1D334B8Bb183  (100 ETH)
계정 3 (개인 사용자):        0x0E217137e76482521f72E845Ce24e55B01ce7D8B  (100 ETH)
```

---

## Laravel 11 프로젝트

### .env 주요 설정
```
BESU_RPC_URL=http://192.168.56.101:8545
BESU_CHAIN_ID=1337
CONTRACT_ADDRESS=0xBC69Cf59bbF7728d0C2984398f5A6C4E7D1DC437
```

### 설치된 패키지
```
web3p/web3.php       → 백엔드 Besu RPC 통신
kornrunner/keccak    → 해시 연산
yajra/laravel-oci8   → Oracle DB 연결
```

### DB 테이블 (Oracle)
```
T_USERS    → wallet_address VARCHAR2(42) 컬럼 포함
T_SONGS
T_HOLDERS
T_LICENSES
```

### 구현 완료 파일
```
app/Services/BesuService.php      → Besu RPC 통신
app/Services/ContractService.php  → web3p/web3.php 컨트랙트 호출
app/Http/Controllers/Controller.php
config/besu.php
resources/views/index.blade.php   → ethers.js CDN + MetaMask 연동
```

### index.blade.php 구현 기능
```
지갑 연결/해제 (MetaMask)
1. 곡 등록               → 지갑 필요 (트랜잭션)
2. 지분율 설정           → 지갑 필요 (트랜잭션, 등록자만)
3. 라이선스 구매         → 지갑 필요 (트랜잭션 + ETH 송금)
4. 곡 정보 조회          → 지갑 불필요 (read-only)
5. 지분율 조회           → 지갑 불필요 (read-only)
6. 라이선스 구매 이력    → 지갑 불필요 (이벤트 로그)
7. 정산 이력             → 지갑 불필요 (이벤트 로그, songId/지갑 필터)
8. 전체 곡 수            → 지갑 불필요 (read-only)
```

---

## 다음 진행할 것
1. 로그인/회원가입 구현 (이메일 + 비밀번호)
2. 로그인 후 MetaMask 지갑 연결 → wallet_address DB 저장
3. 권한 구조 적용
   - 비로그인: 조회만 가능
   - 로그인: 내 이력 조회
   - 로그인 + 지갑 연결: 트랜잭션 가능
4. 신규 회원가입 시 가스비 Faucet 기능
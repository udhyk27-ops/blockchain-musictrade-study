# 블록체인 음악 저작권 정산 시스템 - SETUP

---

## 1. 블록체인 환경

### Hyperledger Besu
- VM IP: `192.168.56.101:8545`
- chainId: `1337`
- 합의 알고리즘: QBFT
- blockperiodseconds: `30` (승인 기반 서비스 기준)
- requesttimeoutseconds: `10`

### 노드 실행 명령어
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
  --p2p-enabled=false \
  --pruning-enabled=true \
  --pruning-blocks-retained=1024 \
  --logging=INFO
```

besu \
  --data-path=node/data \
  --genesis-file=genesis.json \
  --rpc-http-enabled=true \
  --rpc-http-api=ETH,NET,QBFT \
  --host-allowlist="10.84.255.1" \
  --rpc-http-cors-origins="10.84.255.1" \
  --rpc-http-host=0.0.0.0 \
  --rpc-http-port=8545 \
  --min-gas-price=0 \
  --p2p-enabled=true \
  --p2p-port=30303 \
  --logging=INFO


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
    "zeroBaseFee": true,
    "qbft": {
      "blockperiodseconds": 30,
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
  "extraData": "현재 노드 주소로 생성한 값",
  "alloc": {}
}
```

---

## 2. 체인 초기화 방법

### 안전한 리셋 (key 유지 - 노드 주소 변경 없음)
```bash
rm -rf node1/data/database
rm -rf node1/data/caches
rm -f node1/data/DATABASE_METADATA.json
```

> key 파일은 절대 삭제하지 않는다. key 파일이 삭제되면 노드 주소가 바뀌고 extraData 재생성이 필요하다.

### extraData 재생성 방법
```bash
# 1. 현재 노드 주소 확인 (로그에서 Node address 확인)
# 2. toEncode.json 생성
cat > toEncode.json << 'EOF'
["0x노드주소"]
EOF

# 3. extraData 생성
besu rlp encode --from=toEncode.json --type=QBFT_EXTRA_DATA

# 4. 출력값을 genesis.json extraData에 교체
# 5. 체인 데이터 삭제 후 노드 재시작
rm toEncode.json
```

### 블록 생성 확인
```bash
curl -s -X POST http://192.168.56.101:8545 \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}'
# 0x0에서 올라가면 정상
```

---

## 3. Hardhat 컨트랙트 배포

### 초기 배포 순서
```
1. Besu 노드 실행 (블록 생성 확인)
2. npx hardhat clean
3. npx hardhat compile
4. npx hardhat run scripts/deploy.js --network besu
5. 출력된 컨트랙트 주소 → .env CONTRACT_ADDRESS 교체
```

### hardhat.config.js
```javascript
require("@nomicfoundation/hardhat-toolbox");
require("dotenv").config();

module.exports = {
  solidity: "0.8.19",
  networks: {
    besu: {
      url: process.env.BESU_RPC_URL,
      chainId: 1337,
      accounts: [process.env.DEPLOYER_PRIVATE_KEY],
      gasPrice: 0,
    },
  },
};
```

### .env (Hardhat)
```
BESU_RPC_URL=http://192.168.56.101:8545
DEPLOYER_PRIVATE_KEY=0x배포계정개인키
```

### 재배포 시 DB도 함께 초기화
```sql
TRUNCATE TABLE T_ROYALTY_HISTORY;
TRUNCATE TABLE T_LICENSE_PURCHASE;
TRUNCATE TABLE T_SONG_HOLDER;
TRUNCATE TABLE T_SONG;
```

---

## 4. 컨트랙트 정보

### 역할 상수
| 값 | 역할 |
|---|---|
| 1 | 음반 제작사 |
| 2 | 작곡가 |
| 3 | 작사가 |
| 4 | 가수 |
| 5 | 편곡자 |

### 주요 함수
| 함수 | 설명 |
|---|---|
| `registerSong(title)` | 곡 등록 (nonpayable) |
| `setShares(songId, wallets[], roles[], shares[])` | 지분율 설정 (등록자만, active=false 상태에서만 1회) |
| `purchaseLicense(songId, amount)` | 라이선스 구매 + 자동 정산 (nonpayable) |

### 이벤트
```
SongRegistered(songId, title, producer)
SharesSet(songId)
SongActivated(songId, title)
LicensePurchased(songId, buyer, amount, timestamp)
RoyaltyPaid(songId, recipient, role, amount)
```

### 지분율 규칙
- 컨트랙트 내부: 10000 basis point (10000 = 100%)
- setShares 후 곡이 active 상태로 변경됨
- active 상태에서는 setShares 재호출 불가

---

## 5. Laravel 11 프로젝트

### .env 주요 설정
```
BESU_RPC_URL=http://192.168.56.101:8545
BESU_CHAIN_ID=1337
CONTRACT_ADDRESS=0x배포된컨트랙트주소
```

### 설치된 패키지
| 패키지 | 용도 |
|---|---|
| `web3p/ethereum-tx` | 트랜잭션 ECDSA 서명 |
| `web3p/ethereum-util` | 지갑 생성 (개인키 → 주소 도출) |
| `web3p/web3.php` | ABI 인코딩 |
| `yajra/laravel-oci8` | Oracle DB 연결 |

### 구현 파일 목록
```
app/Services/BesuService.php        → Besu JSON-RPC 통신
app/Services/ContractService.php    → ABI 인코딩 (calldata 생성)
app/Services/WalletService.php      → 지갑 생성 / 트랜잭션 서명
app/Models/User.php                 → T_USER
app/Models/Song.php                 → T_SONG
app/Models/SongHolder.php           → T_SONG_HOLDER
app/Models/LicensePurchase.php      → T_LICENSE_PURCHASE
app/Models/RoyaltyHistory.php       → T_ROYALTY_HISTORY
app/Http/Controllers/Controller.php
app/Http/Controllers/LoginController.php
config/besu.php
resources/views/index.blade.php
resources/views/login.blade.php
resources/views/register.blade.php
```

### DB 테이블 (Oracle)
```
T_USER             → F_WALLET_ADDRESS, F_PRIVATE_KEY(암호화) 포함
T_SONG
T_SONG_HOLDER
T_LICENSE_PURCHASE
T_ROYALTY_HISTORY
T_APPROVAL         → 승인 요청 (추후 구현)
T_APPROVAL_MEMBER  → 승인자별 상태 (추후 구현)
```

---

## 6. 서버 사이드 서명 방식

```
회원가입
→ 서버에서 Ethereum 규격 지갑 자동 생성
   (random_bytes(32) → secp256k1 → keccak256 → 주소)
→ 개인키 AES-256 암호화 후 DB 저장
→ 개인키는 프론트로 절대 전송하지 않음

트랜잭션 흐름
→ DB에서 개인키 복호화
→ nonce / gasPrice 조회 (BesuService)
→ calldata ABI 인코딩 (ContractService)
→ ECDSA 서명 (WalletService)
→ eth_sendRawTransaction (BesuService)
→ receipt 폴링 (최대 60초)
→ status: 0x1 확인 후 DB 저장

조회
→ 모든 조회는 DB에서만 (Besu RPC 호출 없음)
→ 트랜잭션 성공 후 DB에 동기화된 데이터 사용
```

---

## 7. 추후 구현 예정

### 승인 기반 수정 흐름
```
수정 요청
→ T_APPROVAL에 임시 저장 (블록체인 미등록)
→ 연관 사용자에게 메일 전송
→ 전원 승인 완료
→ 블록체인 트랜잭션 전송
→ receipt 확인 후 DB 저장
```

### Laravel Queue 적용
```
트랜잭션 백그라운드 처리
→ txHash 즉시 반환
→ 큐에서 receipt 폴링
→ 완료 시 사용자 알림
```
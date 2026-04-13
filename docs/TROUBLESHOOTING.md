# Troubleshooting

## QBFT 블록 생성 안 되는 문제

### 원인
node1/data 전체 삭제 시 key 파일도 삭제되어 노드 주소 변경
→ extraData의 validator 주소와 불일치 → 블록 생성 불가

### 해결
1. 현재 노드 주소 확인 (로그에서 Node address)
2. extraData 재생성
3. genesis.json 교체
4. key 파일 유지하고 나머지만 삭제
5. 노드 재시작

### 체인 초기화 시 올바른 삭제 방법
rm -rf node1/data/database
rm -rf node1/data/caches
rm -f node1/data/DATABASE_METADATA.json
(key 파일 절대 삭제 금지)





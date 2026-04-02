// SPDX-License-Identifier: MIT
pragma solidity ^0.8.19;

contract MusicRoyalty {

    // 역할 상수
    uint8 constant ROLE_PRODUCER   = 1; // 음반 제작사
    uint8 constant ROLE_COMPOSER   = 2; // 작곡가
    uint8 constant ROLE_LYRICIST   = 3; // 작사가
    uint8 constant ROLE_VOCALIST   = 4; // 가수
    uint8 constant ROLE_ARRANGER   = 5; // 편곡자

    struct RightsHolder {
        address wallet;
        uint8   role;
        uint256 share;  // basis points (10000 = 100%)
    }

    struct Song {
        uint256        id;
        string         title;
        address        producer;
        bool           active;
        uint256        totalRevenue;
        RightsHolder[] holders;
    }

    uint256 private _counter;
    mapping(uint256 => Song) private _songs;
    mapping(uint256 => mapping(address => bool)) private _isHolder;
    mapping(uint256 => mapping(address => uint256)) private _holderIdx;

    // 이벤트
    event SongRegistered(uint256 indexed songId, string title, address indexed producer);
    event SharesSet(uint256 indexed songId);
    event SongActivated(uint256 indexed songId, string title);
    event LicensePurchased(uint256 indexed songId, address indexed buyer, uint256 amount, uint256 timestamp);
    event RoyaltyPaid(uint256 indexed songId, address indexed recipient, uint8 role, uint256 amount);

    modifier onlyProducer(uint256 songId) {
        require(_songs[songId].producer == msg.sender, "Not producer");
        _;
    }

    modifier exists(uint256 songId) {
        require(_songs[songId].id != 0, "Song not found");
        _;
    }

    modifier isActive(uint256 songId) {
        require(_songs[songId].active, "Song not active");
        _;
    }

    // ── 곡 등록 ──────────────────────────────
    function registerSong(string calldata title) external returns (uint256) {
        _counter++;
        Song storage s = _songs[_counter];
        s.id       = _counter;
        s.title    = title;
        s.producer = msg.sender;

        emit SongRegistered(_counter, title, msg.sender);
        return _counter;
    }

    // ── 지분율 설정 + 즉시 활성화 ────────────
    function setShares(
        uint256           songId,
        address[] calldata wallets,
        uint8[]   calldata roles,
        uint256[] calldata shares
    ) external exists(songId) onlyProducer(songId) {
        require(!_songs[songId].active, "Already active");
        require(wallets.length == roles.length && roles.length == shares.length, "Length mismatch");

        uint256 total;
        for (uint256 i = 0; i < shares.length; i++) total += shares[i];
        require(total == 10000, "Shares must sum to 10000");

        Song storage s = _songs[songId];
        delete s.holders;

        for (uint256 i = 0; i < wallets.length; i++) {
            s.holders.push(RightsHolder(wallets[i], roles[i], shares[i]));
            _isHolder[songId][wallets[i]] = true;
            _holderIdx[songId][wallets[i]] = i;
        }

        s.active = true;

        emit SharesSet(songId);
        emit SongActivated(songId, s.title);
    }

    // ── 개인 라이선스 구매 + 자동 정산 ───────
    function purchaseLicense(uint256 songId)
        external payable exists(songId) isActive(songId)
    {
        require(msg.value > 0, "Payment required");

        emit LicensePurchased(songId, msg.sender, msg.value, block.timestamp);
        _distribute(songId, msg.value);
    }

    // ── 내부 분배 로직 ────────────────────────
    function _distribute(uint256 songId, uint256 amount) internal {
        Song storage s = _songs[songId];
        s.totalRevenue += amount;

        uint256 distributed;
        for (uint256 i = 0; i < s.holders.length; i++) {
            uint256 payout = (i == s.holders.length - 1)
                ? amount - distributed
                : (amount * s.holders[i].share) / 10000;

            if (payout > 0) {
                (bool ok, ) = s.holders[i].wallet.call{value: payout}("");
                require(ok, "Transfer failed");
                emit RoyaltyPaid(songId, s.holders[i].wallet, s.holders[i].role, payout);
            }
            distributed += payout;
        }
    }

    // ── 조회 ─────────────────────────────────
    function getSongInfo(uint256 songId)
        external view exists(songId)
        returns (string memory title, address producer, bool active, uint256 totalRevenue, uint256 holderCount)
    {
        Song storage s = _songs[songId];
        return (s.title, s.producer, s.active, s.totalRevenue, s.holders.length);
    }

    function getHolders(uint256 songId)
        external view exists(songId)
        returns (address[] memory wallets, uint8[] memory roles, uint256[] memory shares)
    {
        Song storage s = _songs[songId];
        uint256 len = s.holders.length;
        wallets = new address[](len);
        roles   = new uint8[](len);
        shares  = new uint256[](len);
        for (uint256 i = 0; i < len; i++) {
            wallets[i] = s.holders[i].wallet;
            roles[i]   = s.holders[i].role;
            shares[i]  = s.holders[i].share;
        }
    }

    function getSongCount() external view returns (uint256) {
        return _counter;
    }
}
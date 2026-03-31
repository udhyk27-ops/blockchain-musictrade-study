// SPDX-License-Identifier: MIT
pragma solidity ^0.8.20;

contract MusicTrade {

    struct Song {
        uint256 id;
        string  title;
        string  artist;
        string  genre;
        address owner;
        uint256 price;
        bool    forSale;
        uint256 registeredAt;
    }

    struct TradeRecord {
        uint256 songId;
        address seller;
        address buyer;
        uint256 price;
        uint256 tradedAt;
    }

    uint256 private _nextSongId = 1;

    mapping(uint256 => Song)       public songs;
    mapping(address => uint256[])  private _ownedSongs;
    TradeRecord[]                  private _tradeHistory;

    event SongRegistered(uint256 indexed id, address indexed owner, string title);
    event SongListed    (uint256 indexed id, uint256 price);
    event SongDelisted  (uint256 indexed id);
    event SongPurchased (uint256 indexed id, address indexed seller, address indexed buyer, uint256 price);

    modifier onlyOwner(uint256 songId) {
        require(songs[songId].owner == msg.sender, "Not the song owner");
        _;
    }

    modifier exists(uint256 songId) {
        require(songId > 0 && songId < _nextSongId, "Song does not exist");
        _;
    }

    function registerSong(
        string calldata title,
        string calldata artist,
        string calldata genre
    ) external returns (uint256) {
        require(bytes(title).length > 0,  "Title required");
        require(bytes(artist).length > 0, "Artist required");

        uint256 songId = _nextSongId++;

        songs[songId] = Song({
            id:           songId,
            title:        title,
            artist:       artist,
            genre:        genre,
            owner:        msg.sender,
            price:        0,
            forSale:      false,
            registeredAt: block.timestamp
        });

        _ownedSongs[msg.sender].push(songId);
        emit SongRegistered(songId, msg.sender, title);
        return songId;
    }

    function listForSale(uint256 songId, uint256 price)
        external exists(songId) onlyOwner(songId)
    {
        require(price > 0, "Price must be > 0");
        songs[songId].price   = price;
        songs[songId].forSale = true;
        emit SongListed(songId, price);
    }

    function delistSong(uint256 songId)
        external exists(songId) onlyOwner(songId)
    {
        require(songs[songId].forSale, "Not listed for sale");
        songs[songId].price   = 0;
        songs[songId].forSale = false;
        emit SongDelisted(songId);
    }

    function buySong(uint256 songId) external payable exists(songId) {
        Song storage song = songs[songId];
        require(song.forSale,              "Song not for sale");
        require(msg.sender != song.owner,  "Owner cannot buy own song");
        require(msg.value == song.price,   "Incorrect payment amount");

        address seller    = song.owner;
        uint256 salePrice = song.price;

        _removeFromOwned(seller, songId);
        _ownedSongs[msg.sender].push(songId);

        song.owner   = msg.sender;
        song.forSale = false;
        song.price   = 0;

        payable(seller).transfer(salePrice);

        _tradeHistory.push(TradeRecord({
            songId:   songId,
            seller:   seller,
            buyer:    msg.sender,
            price:    salePrice,
            tradedAt: block.timestamp
        }));

        emit SongPurchased(songId, seller, msg.sender, salePrice);
    }

    function getSong(uint256 songId)
        external view exists(songId) returns (Song memory)
    {
        return songs[songId];
    }

    function totalSongs() external view returns (uint256) {
        return _nextSongId - 1;
    }

    function getForSaleList() external view returns (uint256[] memory) {
        uint256 total = _nextSongId - 1;
        uint256 count = 0;
        for (uint256 i = 1; i <= total; i++) {
            if (songs[i].forSale) count++;
        }
        uint256[] memory result = new uint256[](count);
        uint256 idx = 0;
        for (uint256 i = 1; i <= total; i++) {
            if (songs[i].forSale) result[idx++] = i;
        }
        return result;
    }

    function getSongsByOwner(address owner)
        external view returns (uint256[] memory)
    {
        return _ownedSongs[owner];
    }

    function getTradeHistory() external view returns (TradeRecord[] memory) {
        return _tradeHistory;
    }

    function getTradeHistoryBySong(uint256 songId)
        external view exists(songId) returns (TradeRecord[] memory)
    {
        uint256 count = 0;
        for (uint256 i = 0; i < _tradeHistory.length; i++) {
            if (_tradeHistory[i].songId == songId) count++;
        }
        TradeRecord[] memory result = new TradeRecord[](count);
        uint256 idx = 0;
        for (uint256 i = 0; i < _tradeHistory.length; i++) {
            if (_tradeHistory[i].songId == songId) result[idx++] = _tradeHistory[i];
        }
        return result;
    }

    function _removeFromOwned(address owner, uint256 songId) internal {
        uint256[] storage arr = _ownedSongs[owner];
        for (uint256 i = 0; i < arr.length; i++) {
            if (arr[i] == songId) {
                arr[i] = arr[arr.length - 1];
                arr.pop();
                break;
            }
        }
    }
}
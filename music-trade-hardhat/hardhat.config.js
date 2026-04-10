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
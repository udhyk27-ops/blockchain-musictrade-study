const { ethers } = require("hardhat");

async function main() {
  const [deployer] = await ethers.getSigners();

  console.log("배포 계정:", deployer.address);
  console.log("계정 잔액:", ethers.formatEther(
    await ethers.provider.getBalance(deployer.address)
  ), "ETH");

  const MusicTrade = await ethers.getContractFactory("MusicTrade");
  const contract = await MusicTrade.deploy();
  await contract.waitForDeployment();

  const address = await contract.getAddress();
  console.log("MusicTrade 배포 완료:", address);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
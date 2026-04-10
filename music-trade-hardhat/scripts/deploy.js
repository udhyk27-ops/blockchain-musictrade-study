const hre = require("hardhat");

async function main() {
  const [deployer] = await hre.ethers.getSigners();
  console.log("배포 계정:", deployer.address);

  const MusicRoyalty = await hre.ethers.getContractFactory("MusicRoyalty");
  const contract = await MusicRoyalty.deploy();
  await contract.waitForDeployment();

  const address = await contract.getAddress();
  console.log("MusicRoyalty 배포 주소:", address);
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
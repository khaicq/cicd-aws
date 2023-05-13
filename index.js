const express = require("express");
const cors = require("cors");

const startSerser = () => {
  console.log("11111");
  const app = express();
  app.use(cors());
  app.use(express.urlencoded({ extended: false, limit: "50mb" }));
  app.use(express.json({ limit: "50mb" }));

  app.use(`/`, (req, res) => {
    console.log("--1111");
    return res
      .status(200)
      .json({ message: "The server is running successfully" });
  });

  app.use(`/test`, (req, res) => {
    console.log("--1111");
    return res.status(200).json({ message: "test result" });
  });

  app.listen(8088, () => {
    console.log(`Server is running on prort 8088`);
  });
};

startSerser();

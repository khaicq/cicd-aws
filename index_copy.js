const express = require("express");
const cors = require("cors");

const startSerser = () => {
  console.log("2222");
  const app = express();
  app.use(cors());
  app.use(express.urlencoded({ extended: false, limit: "50mb" }));
  app.use(express.json({ limit: "50mb" }));

  app.use(`/`, (req, res) => {
    console.log("--2222");
    return res
      .status(200)
      .json({ message: "The server is running successfully" });
  });

  app.listen(8089, () => {
    console.log(`Server is running on prort 8089`);
  });
};

startSerser();

echo 'setting my-php-app >> /home/ec2-user/cicd-aws/deploy.log'
pm2 start psa_PHPAPNS-sandbox.php --name my-php-app 
<div align="center">
  <a href="https://github.com/j4ym0/agile-home-dashboard/releases">
    <img alt="agile-home-dashboard" src="https://github.com/j4ym0/agile-home-dashboard/blob/main/readme/logo.png?raw=true" height="75px" />
  </a>
  <h1>Agile Home Dashbard</h1>
  <h4>Octopus Energy Agile tariff dashboard</h4>
  <a href="https://github.com/j4ym0/agile-home-dashboard/releases">
    <img alt="GitHub Tag" src="https://img.shields.io/github/v/tag/j4ym0/agile-home-dashboard">
  </a>&nbsp;
  <a href="https://hub.docker.com/r/j4ym0/agile-home-dashboard">
    <img alt="Pulls from DockerHub" src="https://img.shields.io/docker/pulls/j4ym0/agile-home-dashboard.svg?style=flat-square" />
  </a>
</div>

You’ll need an Octopus Energy account to get started. Don’t have one yet? Switch using my [Referal Link](https://share.octopus.energy/brave-sage-915), and we’ll both get £50 credit (or £100 each for businesses)!

## Features

- Secure with a login
- Save your data localy
- Historical usage visualization (daily, weekly, monthly)
- Cost tracking
- Tariff comparison with standard tariff
- Easy visualise tariff pricing

## Screenshots

![Dashboard Screenshot](https://github.com/j4ym0/agile-home-dashboard/blob/main/readme/screenshot1.png?raw=true)
*Main dashboard view showing current usage*

# Launch the container

Simple instance:
```bash
docker run -d --name=aglie-home-dashboard --restart unless-stopped \
-p 80:80 \
j4ym0/agile-home-dashboard
```

Custom configuration file:
```bash
docker run -d --name=aglie-home-dashboard --restart unless-stopped \
-v /My/Datbase/Folder/config.php:/var/www/html/config.php \
-p 80:80 \
j4ym0/agile-home-dashboard
```

Persistent database volume:
```bash
docker run -d --name=aglie-home-dashboard --restart unless-stopped \
-v /My/Datbase/Folder/config.php:/var/www/html/config.php \
-v /My/Datbase/Folder/:/database \
-p 80:80 \
j4ym0/agile-home-dashboard
```

Override custom config:
```bash
docker run -d --name=aglie-home-dashboard --restart unless-stopped \
-v /My/Datbase/Folder/config.php:/var/www/html/config.php \
-v /My/Datbase/Folder/:/database \
-e "SECURE_LOGIN=true"
-p 80:80 \
j4ym0/agile-home-dashboard
```

Note that you can:
 - Use `-p 80:80/tcp` to access the HTTP webpage, changin the first 80 to the port you require
 - Access the web dashboard from [http://localhost](http://localhost)

## TODO

 - Add smartlife support
 - Add live data from home mini
 - Price triggers for smartlife
 - import meter data 

## Issues and features 

If you find a issue of have a feature you would like adding, add an issue to start a discussion.

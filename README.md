# mirror


### TODO

- https://github.com/mevdschee/php-crud-api/blob/main/build.php
- https://github.com/nikic/FastRoute


### git ftp
```sh
curl -L -o /bin/git-ftp https://github.com/git-ftp/git-ftp/master/git-ftp 
chmod 755 /bin/git-ftp

git config git-ftp.url "ftp://ftp.example.net:21/public_html" && \
git config git-ftp.user "ftp-user" && \
git config git-ftp.password "secr3t"

git ftp init
git ftp push

git ftp push -u username -p password ftp://host.example.com[:<port>][/<remote path>]
```

### nosql lib

- [dwgebler/doclite](https://github.com/dwgebler/doclite)
- [atk4/data](https://github.com/atk4/data)
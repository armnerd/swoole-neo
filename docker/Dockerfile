FROM ubuntu

# Timezone
ENV TZ=Asia/Shanghai
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# sources
COPY ./config/sources.list /etc/apt/sources.list

# update
RUN apt-get update && apt-get install -y software-properties-common

# install
RUN apt-get install -y vim wget \
    php php-dev php-fpm composer\
    php-mysql php-curl php-mbstring php-gd && \
    mkdir -p /run/php/ && mkdir /work

# swoole
RUN cd ~ && wget https://pecl.php.net/get/swoole-4.3.4.tgz && \
    tar -zxvf swoole-4.3.4.tgz && cd swoole-4.3.4 && \
    phpize && ./configure --with-php-config=/usr/bin/php-config && \
    make && make install && \
    cd ~ && rm swoole-4.3.4.tgz && rm -rf swoole-4.3.4 && rm package.xml

# config
COPY ./config/www.conf /etc/php/7.3/fpm/pool.d/www.conf
COPY ./config/php.ini /etc/php/7.3/cli/php.ini

WORKDIR /work

EXPOSE 9501

ENTRYPOINT ["php", "/work/NeoSwoole/bootstrap.php"]

#!/usr/bin/env bash

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress}
WP_TMP_DIR=${WP_TMP_DIR-/tmp/wordpress-src}

set -ex

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

download_wp() {
    if [ -d $WP_TMP_DIR ]; then
        return;
    fi

    mkdir -p $WP_TMP_DIR

    if [[ $WP_VERSION =~ [0-9]+\.[0-9]+(\.[0-9]+)? ]]; then
        WP_VERSION_TAG="$WP_VERSION"
    elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
        WP_VERSION_TAG="master"
    else
        # http serves a single offer, whereas https serves multiple. we only want one
        download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
        LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
        if [[ -z "$LATEST_VERSION" ]]; then
            echo "Latest WordPress version could not be found"
            exit 1
        fi
        WP_VERSION_TAG="$LATEST_VERSION"
    fi

    git clone https://github.com/WordPress/wordpress-develop.git $WP_TMP_DIR
    cd $WP_TMP_DIR
    git checkout $WP_VERSION_TAG
}

install_wp() {
	if [ -d $WP_CORE_DIR ]; then
		return;
	fi

	mkdir -p $WP_CORE_DIR

    cp -R $WP_TMP_DIR/src/* $WP_CORE_DIR/

	download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php
}

install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i .bak'
	else
		local ioption='-i'
	fi

	# set up testing suite if it doesn't yet exist
	if [ ! -d $WP_TESTS_DIR ]; then
		# set up testing suite
		mkdir -p $WP_TESTS_DIR/includes
        cp -R $WP_TMP_DIR/tests/phpunit/includes/* $WP_TESTS_DIR/includes/
	fi

	cd $WP_TESTS_DIR

	if [ ! -f wp-tests-config.php ]; then
    	cp -R $WP_TMP_DIR/wp-tests-config-sample.php $WP_TESTS_DIR/wp-tests-config.php
		sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
		sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
	fi

}

install_db() {
	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [ $(echo $DB_SOCK_OR_PORT | grep -e '^[0-9]\{1,\}$') ]; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

    # drop database if exists
    RESULT=`mysqlshow --user="$DB_USER" --password="$DB_PASS"$EXTRA | grep -v Wildcard | grep -o $DB_NAME`
    if [ "$RESULT" == "$DB_NAME" ]; then
        echo y | mysqladmin  --user="$DB_USER" --password="$DB_PASS"$EXTRA DROP $DB_NAME
    fi

	# create database
	mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
}

download_wp
install_wp
install_test_suite
install_db
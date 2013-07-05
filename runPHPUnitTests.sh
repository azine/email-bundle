#!/bin/bash -e
bundleDir=$(pwd)
clear
echo ""
echo ""
echo ""
echo ""
echo ""
echo ""
echo "changing to project root direcotry to execute tests."
cd ../../../../../

echo ""
echo "looking for phpunit installation."
echo ""
if ( hash phpunit 2>/dev/null )
then
	echo "system installation found."
	cmd=phpunit

elif [ -f vendor/phpunit/phpunit/composer/bin/phpunit ]
then
	echo "symfony phpunit bundle found"
	cmd=vendor/phpunit/phpunit/composer/bin/phpunit

else
	echo >&2 "phpunit could not be found.  Aborting."; 
	cd $bundleDir
	exit 1;
fi

echo ""
echo "executing the tests for $bundleDir"
$cmd -c app $bundleDir || true

cd $bundleDir



#!/bin/sh

php -d include_path=.:.. ./random_tests.php 2>&1 | tee output.log

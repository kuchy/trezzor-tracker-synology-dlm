
MODULE_NAME=$(shell grep -w name INFO | cut -d ":" -f 2 | sed -e 's/[",\ ]*//g' )
dlm=${MODULE_NAME}.dlm

all: ${dlm}

${dlm}: INFO search.php
	tar zcf ${dlm} $?
<?php

add_filter("pods_admin_capabilities", function ($pods_admin_capabilities, $cap) {
	$pods_admin_capabilities[] = "administrator";
	return $pods_admin_capabilities;
}, 10, 2);

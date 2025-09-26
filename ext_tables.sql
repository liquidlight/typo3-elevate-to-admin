#
# Table structure for table 'be_users'
#
CREATE TABLE be_users (
	tx_elevatetoadmin_is_possible_admin tinyint(1) DEFAULT '0' NOT NULL,
	tx_elevatetoadmin_admin_since int(11) unsigned DEFAULT '0' NOT NULL
);

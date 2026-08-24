CREATE TABLE tx_flightops_domain_model_departure (
    flight_number varchar(10) DEFAULT '' NOT NULL,
    destination varchar(60) DEFAULT '' NOT NULL,
    crew_mail varchar(255) DEFAULT '' NOT NULL,
    seats int(11) DEFAULT '0' NOT NULL,
    scheduled int(11) DEFAULT '0' NOT NULL,
    booking_link varchar(2048) DEFAULT '' NOT NULL,
    livery_color varchar(7) DEFAULT '' NOT NULL,
    status varchar(20) DEFAULT '' NOT NULL,
    notes text,
    cruser_id int(11) unsigned DEFAULT '0' NOT NULL
);

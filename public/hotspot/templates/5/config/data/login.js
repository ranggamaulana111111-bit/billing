window.loginConfig = {
    defaultForm: 0, // 0 = Voucher, 1 = Member

    optionLogin: {
        enabled: 1
    },

    voucher: {
        value: "default",
        type: "text"
    },

    member: {
        value: "default",
        type: "text"
    },

    scan_qrcode: {
        enabled: 1,
        url: "https://eriiksanjaya.github.io/qr"
    },

    redirect: {
        enabled: 0,
        url: "https://youtube.com",
    },

    label: {
        menu: {
            voucher: "Voucher",
            member: "Member",
            trial: "Trial",
            scan: "Scan",
        },
        title: {
            voucher: "Login Voucher",
            member: "Login Member",
        },
        button: {
            login: "Login",
            logout: "Logout",
        },
        form: {
            voucher_placeholder: "Voucher",
            member_placeholder: "Username",
            password_placeholder: "Password",
        },
    }
};

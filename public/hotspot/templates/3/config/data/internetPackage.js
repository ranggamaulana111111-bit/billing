window.packageConfig = {
    enabled: 1,

    defaultOpen: 1,

    default: 'voucher',

    title: {
        enabled: 1,
        text: "Paket Internet"
    },

    tagLine: {
        enabled: 1,
        text: "299+ puas dengan internet kami"
    },

    tabs: {
        enabled: 1,

        labels: {
            voucher: "Voucher",
            member: "Member"
        }
    },

    // Data - Voucher
    voucher: [
        { validity: "12 Jam", name: "Unlimited", price: "Rp 2.000", link: "" },
        { validity: "24 Jam", name: "Unlimited", price: "Rp 3.000", link: "" },
        { validity: "1 Minggu", name: "Unlimited", price: "Rp 20.000", link: "" },
        { validity: "1 Bulan", name: "Unlimited", price: "Rp 40.000", link: "" },
        ],

    // Data - Member
    member: [
        { validity: "1 Bulan", name: "2Mbps", price: "Rp 40.000", link: "" },
        { validity: "2 Bulan", name: "2Mbps", price: "Rp 80.000", link: "" },
    ],

    buyButton: {
        enabled: 1,
        title: "Beli",
        link: "https://wa.me/89531559066",
        text: "Halo admin, saya mau beli paket"
    }
};

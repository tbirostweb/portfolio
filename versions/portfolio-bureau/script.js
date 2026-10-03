
    setTimeout(() => {
    const messageBox = document.querySelector(".message");
    if (messageBox) {
    messageBox.style.opacity = "0";
    setTimeout(() => messageBox.style.display = "none", 500);
}
}, 5000);

    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener("click", function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute("href"));
            if (target) {
                target.scrollIntoView({ behavior: "smooth" });
            }
        });
    });

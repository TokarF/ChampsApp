let ujMeccs = document.querySelector("#uj-meccs");
let meccsek = document.querySelector("#meccsek");

let ujMeccsSor = `
                <div class="meccs">
                    <div class="mb-3">
                        <label for="recipient-name" class="col-form-label fw-bold">Hazai:</label>
                        <select name="hazai[]" class="form-select bg-secondary text-white" required>
                            <option value="" selected disabled>Kérem válasszon</option>
                            <?php foreach ($params["teams"] as $team) : ?>
                                <option value="<?= $team["id"] ?>"><?= $team["nev"] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="vendeg" class="col-form-label fw-bold">Vendég:</label>
                        <select name="vendeg[]" class="form-select bg-secondary text-white" required>
                            <option value="" selected disabled>Kérem válasszon</option>
                            <?php foreach ($params["teams"] as $team) : ?>
                                <option value="<?= $team["id"] ?>"><?= $team["nev"] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="vendeg" class="col-form-label fw-bold">Csoport:</label>
                        <input name="csoport[]" type="text" class="form-control bg-secondary text-white" required>
                    </div>
                    <div class="mb-3">
                        <label for="kezdes" class="col-form-label fw-bold">Kezdés:</label>
                        <input name="kezdes[]" type="datetime-local" class="form-control bg-secondary text-white" required>
                    </div>
                    <div class="mb-3 d-grid">
                        <button onclick="deleteRow(this)"  class="btn btn-block btn-outline-danger"><span class="p-2"><i class="fas fa-times"></i></span> Törlés</button>
                    </div>
                </div>
    `;

ujMeccs.addEventListener("click", () => {
    meccsek.insertAdjacentHTML("beforeend", ujMeccsSor);
})

function deleteRow(current) {
    current.closest(".meccs").remove();
}

function meccsModositas(current) {
    let meccsSzerkeszteseForm = document.querySelector("#meccs-szerkesztese-form");

    meccsSzerkeszteseForm.action = "/meccs-szerkesztes/" + current.dataset.meccsid;

    document.querySelector("#hazai-csapat").innerHTML = current.dataset.hazai;
    document.querySelector("#vendeg-csapat").innerHTML = current.dataset.vendeg;

}

function meccsTorles(current) {
    let meccsTorleseForm = document.querySelector("#meccs-torlese-form");

    meccsTorleseForm.action = "/meccs-torles/" + current.dataset.meccsid;

}

let tips;

function tippLeadasa(form) {
    console.log(form);

    const formData = new FormData(form);



    fetch("/tipp-leadasa/" + form.dataset.tipid, {
            method: 'post',
            body: formData
        })
        .then(response => response.json())
        .then(data => tipsLoad(data))
}

function tippTorlese(form) {
    console.log(form);
    const formData = new FormData(form);
    console.log("/tipp-torles/" + form.dataset.tipid);


    fetch("/tipp-torles/" + form.dataset.tipid, {
            method: 'post',
            body: formData
        })
        .then(response => response.json())
        .then(data => tipsLoad(data))
}
function tipsLoad(data) {
    console.log(data);
    document.querySelector("#sajat-tippek-tabla").tBodies[0].innerHTML = "";
    data.givenTips.forEach(givenTip => {
        document.querySelector("#sajat-tippek-tabla").tBodies[0].innerHTML +=
            `<tr>
                <td>${givenTip.meccsId}</td>
                <td>${givenTip.meccs}</td>
                <td>${givenTip.tipp}</td>
                <td>
                ${givenTip.lejatszott == 0 ? 
                    `<form onsubmit="event.preventDefault();tippTorlese(this)" class="d-inline" data-tipid="${givenTip.id}">
                    <input type="hidden" name="bajnoksag-id" value="${givenTip.bajnoksagId}">
                    <button type="submit" class="btn btn-outline-danger"><i class="fas fa-times"></i></button></form>`
                : ''}
                </td>   
            </tr>`

    });

    document.querySelector("#aktiv-tippek").innerHTML = "";
    data.activeTips.forEach(activeTip => {
        document.querySelector("#aktiv-tippek").innerHTML +=
            `<form onsubmit="event.preventDefault();tippLeadasa(this)" class="tipp-leadas-form" method="POST" data-tipid="${activeTip.id}">
                <input type="hidden" name="bajnoksag-id" value="${activeTip.bajnoksagId}">
                <div class="form-group row">
                    <div class="col-lg-2 mb-3">
                        <label class="form-label lead" for="kezdes">${activeTip.kezdes}</label>
                        <input type="hidden" name="kezdes" value="${activeTip.kezdes}">
                    </div>
                    <label class="col-4 col-lg-2 lead  mb-3" for="hazai-eredmeny">${activeTip.hazai}</label>
                    <div class="col-8 col-lg-2  mb-3">
                        <input class="bg-secondary form-control text-white border-secondary" name="hazai-eredmeny" type="number" min="0" required>
                    </div>
                    <label class="col-4 col-lg-2 lead  mb-3" for="vendeg-eredmeny">${activeTip.vendeg}</label>
                    <div class="col-8 col-lg-2 mb-3">
                        <input class="bg-secondary form-control text-white border-secondary" name="vendeg-eredmeny" type="number" min="0" required="">
                    </div>
                    <div class="col-lg-2 mb-3 d-grid">
                        <button class="btn btn-block btn-outline-danger" type="submit"><span class="p-2"><i class="fas fa-save"></i></span>Mentés</button>
                    </div>
                </div>
            </form>`
    });
}
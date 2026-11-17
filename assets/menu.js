(() => {
    const root = document.querySelector('#menu-list');
    const error = document.querySelector('#menu-error');
    const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(value) || 0);

    function addProduct(product, selectedOptions) {
        const cart = JSON.parse(localStorage.getItem('kafe_cart_v1') || '[]');
        cart.push({
            product_id: Number(product.product_id),
            name: product.name,
            base_price: Number(product.price),
            unit_price: Number(product.price) + selectedOptions.reduce((sum, option) => sum + option.extra, 0),
            qty: 1,
            options: selectedOptions,
            note: ''
        });
        localStorage.setItem('kafe_cart_v1', JSON.stringify(cart));
        const count = document.querySelector('#cart-count');
        if (count) count.textContent = String(cart.reduce((sum, item) => sum + (Number(item.qty) || 0), 0));
    }

    function renderProduct(product, category) {
        const card = document.createElement('article');
        card.className = 'product-card';
        const title = document.createElement('h3');
        title.textContent = product.name;
        const description = document.createElement('p');
        description.textContent = product.description || '';
        const price = document.createElement('p');
        price.className = 'price';
        price.textContent = money(product.price);
        if (typeof product.photo === 'string' && product.photo.startsWith('assets/uploads/')) {
            const photo = document.createElement('img');
            photo.src = product.photo;
            photo.alt = product.name;
            photo.loading = 'lazy';
            photo.className = 'product-photo';
            card.append(photo);
        }
        const form = document.createElement('form');
        form.className = 'product-options';

        (product.option_groups || []).forEach((group) => {
            const label = document.createElement('label');
            label.textContent = group.name + (Number(group.is_required) === 1 ? ' (wajib)' : '');
            const select = document.createElement('select');
            select.dataset.groupId = String(group.group_id);
            select.dataset.groupName = group.name;
            if (Number(group.is_required) === 1) select.required = true;
            const empty = document.createElement('option');
            empty.value = '';
            empty.textContent = 'Pilih ' + group.name;
            select.append(empty);
            (group.values || []).forEach((value) => {
                const option = document.createElement('option');
                option.value = String(value.value_id);
                option.dataset.label = value.label;
                option.dataset.extra = String(value.extra_price);
                option.textContent = value.label + (Number(value.extra_price) > 0 ? ' (+' + money(value.extra_price) + ')' : '');
                select.append(option);
            });
            label.append(select);
            form.append(label);
        });

        const add = document.createElement('button');
        add.type = 'submit';
        const available = Number(product.is_available) === 1 && Number(product.stock_quantity) > 0;
        add.textContent = available ? 'Tambah ke keranjang' : 'Stok habis';
        add.disabled = !available;
        const stock = document.createElement('p');
        stock.className = available ? 'stock-available' : 'stock-empty';
        stock.textContent = available ? 'Tersedia: ' + product.stock_quantity : 'Saat ini tidak tersedia';
        form.append(add);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            if (!form.reportValidity()) return;
            const selected = Array.from(form.querySelectorAll('select')).filter((select) => select.value).map((select) => {
                const option = select.selectedOptions[0];
                return {
                    group_id: Number(select.dataset.groupId),
                    group: select.dataset.groupName,
                    value_id: Number(select.value),
                    label: option.dataset.label,
                    extra: Number(option.dataset.extra)
                };
            });
            addProduct(product, selected);
            add.textContent = 'Ditambahkan';
            window.setTimeout(() => { add.textContent = 'Tambah ke keranjang'; }, 1000);
        });
        card.append(title, description, price, stock, form);
        return card;
    }

    fetch('api/menu.php')
        .then((response) => response.json().then((data) => {
            if (!response.ok) throw new Error(data.error || 'Menu gagal dimuat.');
            return data;
        }))
        .then((data) => {
            (data.categories || []).forEach((category) => {
                const section = document.createElement('section');
                section.className = 'category-section';
                const heading = document.createElement('h2');
                heading.textContent = category.name;
                const description = document.createElement('p');
                description.textContent = category.description || '';
                const products = document.createElement('div');
                products.className = 'product-grid';
                (category.products || []).forEach((product) => products.append(renderProduct(product, category)));
                section.append(heading, description, products);
                root.append(section);
            });
            if (!root.childElementCount) {
                const empty = document.createElement('p');
                empty.textContent = 'Menu belum tersedia.';
                root.append(empty);
            }
        })
        .catch((cause) => {
            error.textContent = cause.message;
            error.hidden = false;
        });
})();

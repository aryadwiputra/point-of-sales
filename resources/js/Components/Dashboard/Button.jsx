import { Link } from '@inertiajs/react'
import React from 'react'
import { useForm } from '@inertiajs/react';
import Swal from 'sweetalert2'

export default function Button({ className, icon, label, type, href, added, url, id, ...props }) {

    const { delete: destroy } = useForm();

    const deleteData = async (url) => {
        Swal.fire({
            title: 'Apakah kamu yakin ingin menghapus data ini ?',
            text: "Data yang dihapus tidak dapat dikembalikan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, tolong hapus!',
            cancelButtonText: 'Tidak'
        }).then((result) => {
            if (result.isConfirmed) {
                destroy(url)

                Swal.fire({
                    title: 'Success!',
                    text: 'Data berhasil dihapus!',
                    icon: 'success',
                    showConfirmButton: false,
                    timer: 1500
                })
            }
        })
    }

    return (
        <>
            {type === 'link' &&
                <Link href={href} className={`${className} px-4 py-2 flex items-center gap-2 rounded-lg text-sm font-semibold text-gray-200`}>
                    {icon} <span className={`${added === true ? 'hidden lg:block' : ''}`}>{label}</span>
                </Link>
            }
            {type === 'button' &&
                <button className={`${className} px-4 py-2 flex items-center gap-1 rounded-lg text-sm font-semibold text-gray-200`} {...props}>
                    {icon} <span className={`${added === true ? 'hidden md:block' : ''}`}>{label}</span>
                </button>
            }
            {type === 'submit' &&
                <button type='submit' className={`${className} px-4 py-2 flex items-center gap-1 rounded-lg text-sm font-semibold`} {...props}>
                    {icon} <span className={`${added === true ? 'hidden lg:block' : ''}`}>{label}</span>
                </button>
            }
            {type === 'delete' &&
                <button onClick={() => deleteData(url)} className={`${className} px-3 py-2 flex items-center gap-1 rounded-lg text-sm font-semibold`} {...props}>
                    {icon}
                </button>
            }
            {type === 'modal' &&
                <button className={`${className} px-3 py-2 flex items-center gap-1 rounded-lg text-sm font-semibold`} {...props}>
                    {icon}
                </button>
            }
            {type === 'edit' &&
                <Link href={href} className={`${className} px-3 py-2 flex items-center gap-1 rounded-lg text-sm font-semibold`} {...props}>
                    {icon}
                </Link>
            }
            {type === 'bulk' &&
                <button {...props} className={`${className} px-4 py-2 flex items-center gap-2 rounded-lg text-sm font-semibold text-gray-200`}>
                    {icon} <span className={`${added === true ? 'hidden lg:block' : ''}`}>{label}</span>
                </button>
            }
        </>
    )
}

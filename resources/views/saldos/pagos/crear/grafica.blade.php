<div>
                    <canvas id="myChart" width="400" height="400"></canvas>
<script>
const ctx = document.getElementById('myChart').getContext('2d');

const dataTmp = [{x: 'Red', net: 100, cogs: 50, gm: 50}, ];
const myChart = new Chart(ctx, {
    type: 'bar',
    data: {
      
        datasets: [{
            label: '# of Red',
            data: dataTmp,
            backgroundColor: [
                'rgba(255, 99, 132, 0.2)',
                
            ],
            borderColor: [
                'rgba(255, 99, 132, 1)',
                
            ],
            borderWidth: 1
            ,parsing: {
                yAxisKey: 'net'
            }
        }
        ,{
            label: '# of Blue',
            data: dataTmp,
            backgroundColor: [
                
                'rgba(54, 162, 235, 0.2)',
                
            ],
            borderColor: [
                
                'rgba(54, 162, 235, 1)',
                
            ],
            borderWidth: 1
            ,parsing: {
                yAxisKey: 'cogs'
            }
        }
        ,{
            label: '# of Yellow',
            data: dataTmp,
            backgroundColor: [
               
                'rgba(255, 206, 86, 0.2)',
                
            ],
            borderColor: [
                
                'rgba(255, 206, 86, 1)',
                
            ],
            borderWidth: 1
            ,parsing: {
                yAxisKey: 'gm'
            }
        }]
    },
    options: {
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
</script>    
                </div> 
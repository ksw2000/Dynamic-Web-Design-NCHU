package main
import(
    "fmt"
    "math/rand"
    "time"
)
func main(){
    format := `INSERT INTO health(studentID, gender, birthdate, height, weight, vision_of_left_eye, vision_of_right_eye, waistline, scoliosis)
    VALUES ("%d", "%s", "%s", %.2f, %.0f, %.1f, %.1f, %.1f, %t);`
    for i:=0; i<10; i++{
        r := rand.New(rand.NewSource(time.Now().UnixNano() + int64(i) + 319414))
        id := i
        gender := "male"
        if r.Float32() > .5{
            gender = "female"
        }
        year := r.Int() % 2 + 1999
        month := r.Int() % 12 + 1
        date := r.Int() % 28 + 1
        birthdate := fmt.Sprintf("%4d-%02d-%02d", year, month, date)
        height := r.Float32() * 30 + 150
        weight := r.Float32() * 50 + 40
        vision_of_left_eye := r.Float32() * 1.1
        vision_of_right_eye := vision_of_left_eye - 0.5 + r.Float32() * 0.5
        waistline := r.Float32() * 30 + 55
        scoliosis := (r.Float32() > .5)
        fmt.Printf(format+"\n\n", id, gender, birthdate, height/100, weight, vision_of_left_eye, vision_of_right_eye, waistline, scoliosis)
    }
}
